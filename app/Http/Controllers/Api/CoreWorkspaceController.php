<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ModuleRecord;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRequest;
use App\Models\Payslip;
use App\Models\User;
use App\Models\WorkCalendar;
use App\Services\ApprovalService;
use App\Support\CoreAccess;
use App\Support\HcisAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CoreWorkspaceController extends Controller
{
    public function bootstrap(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $modules = CoreAccess::modulesFor($employee);
        $today = Attendance::where('employee_id', $employee->id)->whereDate('date', today())->first();
        $leaveRemaining = LeaveBalance::where('employee_id', $employee->id)
            ->join('leave_types', 'leave_types.id', '=', 'leave_balances.leave_type_id')
            ->where('leave_types.deduct_balance', true)
            ->where('year', now()->year)
            ->selectRaw('coalesce(sum(entitled - used), 0) as remaining')
            ->value('remaining');
        $latestPayslip = Payslip::with('period')
            ->where('employee_id', $employee->id)
            ->where('status', 'published')
            ->latest('id')
            ->first();
        $shift = DB::table('shift_schedules')
            ->join('shifts', 'shifts.id', '=', 'shift_schedules.shift_id')
            ->where('shift_schedules.employee_id', $employee->id)
            ->whereDate('shift_schedules.date', today())
            ->select(['shifts.name', 'shifts.start_time', 'shifts.end_time'])
            ->first();
        $teamIds = Employee::where('manager_id', $employee->id)->pluck('id');
        $teamAttendance = Attendance::whereIn('employee_id', $teamIds)
            ->whereDate('date', today())
            ->get();

        return response()->json([
            'product' => ['key' => 'hcis', 'name' => 'Ensys HCIS'],
            'user' => $this->userPayload($employee),
            'modules' => $modules,
            'summary' => [
                'attendance' => $today ? $this->attendancePayload($today) : null,
                'leave_remaining' => (float) $leaveRemaining,
                'latest_payslip' => $latestPayslip ? [
                    'id' => $latestPayslip->id,
                    'period' => $latestPayslip->period?->name,
                    'status' => $latestPayslip->status,
                    'published_at' => $latestPayslip->updated_at?->toIso8601String(),
                ] : null,
                'shift' => $shift ? [
                    'name' => $shift->name,
                    'start_time' => substr((string) $shift->start_time, 0, 5),
                    'end_time' => substr((string) $shift->end_time, 0, 5),
                ] : null,
                'team' => [
                    'total' => $teamIds->count(),
                    'present' => $teamAttendance->whereIn('status', ['present', 'late'])->count(),
                    'leave' => $teamAttendance->whereIn('status', ['leave', 'sick'])->count(),
                    'not_recorded' => max(0, $teamIds->count() - $teamAttendance->count()),
                ],
                'pending_approvals' => $this->pendingApprovalsFor($employee)->count(),
            ],
            'activities' => $this->recentActivities($employee),
            'notifications' => $this->coreNotifications($employee),
            'reference_data' => [
                'leave_types' => LeaveType::where(fn ($query) => $query
                    ->whereNull('company_id')
                    ->orWhere('company_id', $employee->company_id))
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (LeaveType $type) => [
                        'id' => $type->id,
                        'code' => $type->code,
                        'name' => $type->name,
                        'requires_attachment' => (bool) $type->requires_attachment,
                    ])
                    ->values(),
            ],
            'updated_at' => now()->toIso8601String(),
            'version' => 2,
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $employee = $this->authorizeModule($request, 'attendance', 'view');
        $records = Attendance::where('employee_id', $employee->id)
            ->latest('date')
            ->limit(31)
            ->get()
            ->map(fn (Attendance $attendance) => $this->attendancePayload($attendance));

        return response()->json(['data' => $records, 'updated_at' => now()->toIso8601String(), 'version' => 1]);
    }

    public function leaveRequests(Request $request): JsonResponse
    {
        $employee = $this->authorizeModule($request, 'leave', 'view');
        $requests = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (LeaveRequest $leave) => $this->leavePayload($leave));

        return response()->json(['data' => $requests, 'updated_at' => now()->toIso8601String(), 'version' => 1]);
    }

    public function storeLeaveRequest(Request $request, ApprovalService $approval): JsonResponse
    {
        $employee = $this->authorizeModule($request, 'leave', 'create');
        $data = $request->validate([
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $type = LeaveType::whereKey($data['leave_type_id'])
            ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $employee->company_id))
            ->where('is_active', true)
            ->firstOrFail();
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        if ($type->requires_attachment) {
            throw ValidationException::withMessages(['leave_type_id' => 'Jenis cuti ini membutuhkan lampiran dari HCIS.']);
        }

        if ($type->minimum_notice_days && today()->diffInDays($start, false) < $type->minimum_notice_days) {
            throw ValidationException::withMessages(['start_date' => 'Pengajuan membutuhkan pemberitahuan minimal '.$type->minimum_notice_days.' hari.']);
        }

        $days = $this->workingDays($employee->company_id, $start, $end);
        if ($days <= 0) {
            throw ValidationException::withMessages(['start_date' => 'Rentang tidak memiliki hari kerja.']);
        }

        $overlap = LeaveRequest::where('employee_id', $employee->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'Karyawan memiliki pengajuan cuti yang bertumpang tindih.']);
        }

        $requesterId = $this->coreRequesterId($employee);
        $leave = LeaveRequest::create($data + [
            'employee_id' => $employee->id,
            'days' => $days,
            'created_by' => $requesterId,
            'updated_by' => $requesterId,
            'status' => 'draft',
        ]);
        $approval->submit($leave, 'leave', $requesterId);
        activity('core-sync')->performedOn($leave)->withProperties(['module' => 'leave', 'channel' => 'core'])->log('Pengajuan cuti dibuat dari Core');

        return response()->json(['message' => 'Pengajuan cuti berhasil disinkronkan ke HCIS.', 'data' => $this->leavePayload($leave->fresh('leaveType'))], 201);
    }

    public function overtimeRequests(Request $request): JsonResponse
    {
        $employee = $this->authorizeModule($request, 'overtime', 'view');
        $requests = OvertimeRequest::where('employee_id', $employee->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (OvertimeRequest $overtime) => $this->overtimePayload($overtime));

        return response()->json(['data' => $requests, 'updated_at' => now()->toIso8601String(), 'version' => 1]);
    }

    public function storeOvertimeRequest(Request $request, ApprovalService $approval): JsonResponse
    {
        $employee = $this->authorizeModule($request, 'overtime', 'create');
        $data = $request->validate([
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $hours = Carbon::parse($data['date'].' '.$data['start_time'])->diffInMinutes(Carbon::parse($data['date'].' '.$data['end_time'])) / 60;
        $policy = OvertimePolicy::where('company_id', $employee->company_id)->where('is_active', true)->latest()->first();

        if ($policy && $policy->maximum_hours_per_day && $hours > $policy->maximum_hours_per_day) {
            throw ValidationException::withMessages(['end_time' => 'Durasi melebihi batas kebijakan lembur.']);
        }

        $amount = 0;
        if ($policy && $hours >= $policy->minimum_hours) {
            $date = Carbon::parse($data['date']);
            $holiday = WorkCalendar::where('company_id', $employee->company_id)->whereHas('holidays', fn ($query) => $query->whereDate('holiday_date', $date))->exists();
            $multiplier = $holiday ? $policy->holiday_multiplier : ($date->isWeekend() ? $policy->weekend_multiplier : $policy->weekday_multiplier);
            $basic = DB::table('employee_salaries')
                ->join('salary_components', 'salary_components.id', '=', 'employee_salaries.salary_component_id')
                ->where('employee_id', $employee->id)
                ->where('salary_components.code', 'BASIC')
                ->whereDate('effective_date', '<=', $date)
                ->orderByDesc('effective_date')
                ->value('amount') ?: 0;
            $amount = round(($basic / $policy->monthly_divisor) * $hours * $multiplier, 2);
        }

        $requesterId = $this->coreRequesterId($employee);
        $overtime = OvertimeRequest::create($data + [
            'employee_id' => $employee->id,
            'hours' => $hours,
            'overtime_policy_id' => $policy?->id,
            'calculated_amount' => $amount,
            'status' => 'draft',
            'created_by' => $requesterId,
        ]);
        $approval->submit($overtime, 'overtime', $requesterId);
        activity('core-sync')->performedOn($overtime)->withProperties(['module' => 'overtime', 'channel' => 'core'])->log('Pengajuan lembur dibuat dari Core');

        return response()->json(['message' => 'Pengajuan lembur berhasil disinkronkan ke HCIS.', 'data' => $this->overtimePayload($overtime->fresh())], 201);
    }

    public function moduleRecords(Request $request, string $module): JsonResponse
    {
        $moduleKey = $this->coreModuleKey($module);
        $employee = $this->authorizeModule($request, $moduleKey, 'view');
        $records = $this->serviceRecords($employee, $moduleKey);

        if ($records !== null) {
            $genericRecords = in_array($moduleKey, ['claim', 'document'], true)
                ? collect()
                : ModuleRecord::with(['company', 'employee'])
                    ->where('employee_id', $employee->id)
                    ->whereIn('module', HcisAccess::moduleRecordAliases($moduleKey))
                    ->latest()
                    ->limit(50)
                    ->get()
                    ->map(fn (ModuleRecord $record) => $this->moduleRecordPayload($record));
            $records = $records->concat($genericRecords)
                ->unique(fn (array $record) => $record['module'].'-'.$record['id'].'-'.($record['title'] ?? ''))
                ->sortByDesc('record_date')
                ->values()
                ->take(50);

            return response()->json(['data' => $records, 'updated_at' => now()->toIso8601String(), 'version' => 2]);
        }

        abort_unless($this->isModuleRecordBacked($moduleKey), 404, 'Modul ini belum tersedia untuk sinkronisasi.');
        $records = ModuleRecord::with(['company', 'employee'])
            ->where('employee_id', $employee->id)
            ->whereIn('module', HcisAccess::moduleRecordAliases($moduleKey))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ModuleRecord $record) => $this->moduleRecordPayload($record));

        return response()->json(['data' => $records, 'updated_at' => now()->toIso8601String(), 'version' => 1]);
    }

    public function storeModuleRecord(Request $request, string $module): JsonResponse
    {
        $moduleKey = $this->coreModuleKey($module);
        $employee = $this->authorizeModule($request, $moduleKey, $this->moduleRecordWriteAbility($moduleKey));
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'record_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['draft', 'submitted', 'in_review', 'approved', 'rejected', 'completed', 'active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'external_id' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ]);

        if ($moduleKey === 'claim') {
            $claimId = DB::table('claims')->insertGetId([
                'employee_id' => $employee->id,
                'category' => $data['metadata']['category'] ?? $data['title'],
                'claim_date' => $data['record_date'] ?? today(),
                'amount' => $data['amount'] ?? 0,
                'description' => $data['description'] ?? $data['title'],
                'status' => 'submitted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $claim = DB::table('claims')->find($claimId);
            ModuleRecord::create([
                'company_id' => $employee->company_id,
                'module' => 'claim',
                'reference_no' => $this->nextCoreReference('claim'),
                'title' => $data['title'],
                'employee_id' => $employee->id,
                'record_date' => $claim->claim_date,
                'amount' => $claim->amount,
                'status' => $claim->status,
                'details' => [
                    'description' => $claim->description,
                    'source' => 'core',
                    'native_claim_id' => $claimId,
                ],
            ]);
            activity('core-sync')->performedOn($employee)->withProperties(['module' => 'claim', 'claim_id' => $claimId, 'channel' => 'core'])->log('Reimbursement dibuat dari Core');

            return response()->json([
                'message' => 'Reimbursement berhasil disinkronkan ke HCIS.',
                'data' => $this->normalizedRecord($claimId, 'claim', $data['title'], $claim->claim_date, $claim->amount, $claim->status, [
                    'description' => $claim->description,
                    'category' => $claim->category,
                    'source' => 'core',
                ]),
            ], 201);
        }

        if ($moduleKey === 'document') {
            $documentId = DB::table('employee_documents')->insertGetId([
                'employee_id' => $employee->id,
                'type' => $data['metadata']['type'] ?? 'request',
                'title' => $data['title'],
                'description' => $data['description'] ?? 'Permintaan dokumen dari Core.',
                'start_date' => $data['record_date'] ?? today(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            ModuleRecord::create([
                'company_id' => $employee->company_id,
                'module' => 'document',
                'reference_no' => $this->nextCoreReference('document'),
                'title' => $data['title'],
                'employee_id' => $employee->id,
                'record_date' => $data['record_date'] ?? today(),
                'status' => 'submitted',
                'details' => [
                    'description' => $data['description'] ?? 'Permintaan dokumen dari Core.',
                    'source' => 'core',
                    'native_document_id' => $documentId,
                ],
            ]);
            activity('core-sync')->performedOn($employee)->withProperties(['module' => 'document', 'document_id' => $documentId, 'channel' => 'core'])->log('Permintaan dokumen dibuat dari Core');

            return response()->json([
                'message' => 'Permintaan dokumen berhasil disinkronkan ke HCIS.',
                'data' => $this->normalizedRecord($documentId, 'document', $data['title'], $data['record_date'] ?? today(), null, 'submitted', [
                    'description' => $data['description'] ?? null,
                    'source' => 'core',
                ]),
            ], 201);
        }

        abort_unless(
            $this->isModuleRecordBacked($moduleKey) || in_array($moduleKey, ['learning', 'performance'], true),
            422,
            'Modul ini hanya dapat dilihat dari Core.'
        );
        $record = ModuleRecord::create([
            'company_id' => $employee->company_id,
            'module' => HcisAccess::moduleRecordKey($moduleKey),
            'reference_no' => $this->nextCoreReference($moduleKey),
            'title' => $data['title'],
            'employee_id' => $employee->id,
            'record_date' => $data['record_date'] ?? today(),
            'amount' => $data['amount'] ?? null,
            'status' => $data['status'] ?? 'submitted',
            'details' => array_filter([
                'description' => $data['description'] ?? null,
                'source' => 'core',
                'external_id' => $data['external_id'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ], fn ($value) => ! blank($value)),
        ]);
        activity('core-sync')->performedOn($record)->withProperties(['module' => $moduleKey, 'channel' => 'core'])->log('Record modul dibuat dari Core');

        return response()->json(['message' => 'Data Core berhasil disinkronkan ke HCIS.', 'data' => $this->moduleRecordPayload($record->fresh(['company', 'employee']))], 201);
    }

    public function approvals(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        abort_unless(in_array($employee->core_role, ['manager', 'general_manager', 'director'], true), 403, 'Akun ini tidak memiliki akses persetujuan.');

        return response()->json([
            'data' => $this->pendingApprovalsFor($employee)->values(),
            'updated_at' => now()->toIso8601String(),
            'version' => 1,
        ]);
    }

    public function decideApproval(Request $request, int $approvalRequest): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        abort_unless(in_array($employee->core_role, ['manager', 'general_manager', 'director'], true), 403, 'Akun ini tidak memiliki akses persetujuan.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $approval = DB::table('approval_requests')->find($approvalRequest);
        abort_if(! $approval, 404, 'Pengajuan tidak ditemukan.');
        $context = json_decode((string) $approval->context, true) ?: [];
        abort_unless(
            (int) ($context['manager_id'] ?? 0) === $employee->id
                || ($employee->core_role === 'director' && (int) $approval->company_id === $employee->company_id),
            403,
            'Pengajuan ini bukan bagian dari tim Anda.'
        );
        abort_unless(in_array($approval->status, ['submitted', 'pending'], true), 422, 'Pengajuan ini sudah diproses.');

        DB::transaction(function () use ($approval, $data, $employee) {
            DB::table('approval_requests')->where('id', $approval->id)->update([
                'status' => $data['decision'],
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('approval_steps')->where('approval_request_id', $approval->id)->where('status', 'pending')->update([
                'approver_id' => $employee->user?->id,
                'status' => $data['decision'],
                'notes' => $data['notes'] ?? 'Diproses melalui Core.',
                'acted_at' => now(),
                'updated_at' => now(),
            ]);
            $sourceTable = match ($approval->document_type) {
                'leave' => 'leave_requests',
                'overtime' => 'overtime_requests',
                'claim' => 'claims',
                default => null,
            };
            if ($sourceTable) {
                DB::table($sourceTable)->where('id', $approval->reference_id)->update([
                    'status' => $data['decision'],
                    'updated_at' => now(),
                ]);
            }
        });
        activity('core-sync')->performedOn($employee)->withProperties([
            'approval_request_id' => $approval->id,
            'decision' => $data['decision'],
            'channel' => 'core',
        ])->log('Persetujuan diproses dari Core');

        return response()->json(['message' => 'Keputusan berhasil disinkronkan ke HCIS.']);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $employee = $this->authorizeModule($request, 'attendance', 'check_in');
        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $record = Attendance::firstOrCreate(
            ['employee_id' => $employee->id, 'date' => today()],
            ['status' => 'present']
        );

        if ($record->check_in_at) {
            return response()->json(['message' => 'Check-in hari ini sudah tercatat.'], 422);
        }

        $record->update([
            'check_in_at' => now(),
            'latitude_in' => $data['latitude'] ?? null,
            'longitude_in' => $data['longitude'] ?? null,
        ]);
        activity('attendance')->performedOn($record)->withProperties(['channel' => 'core'])->log('Check-in dari Core');

        return response()->json(['message' => 'Check-in berhasil.', 'data' => $this->attendancePayload($record->fresh())]);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $employee = $this->authorizeModule($request, 'attendance', 'check_out');
        $record = Attendance::where('employee_id', $employee->id)->whereDate('date', today())->first();

        if (! $record?->check_in_at) {
            return response()->json(['message' => 'Lakukan check-in terlebih dahulu.'], 422);
        }

        if ($record->check_out_at) {
            return response()->json(['message' => 'Check-out hari ini sudah tercatat.'], 422);
        }

        $record->update(['check_out_at' => now()]);
        activity('attendance')->performedOn($record)->withProperties(['channel' => 'core'])->log('Check-out dari Core');

        return response()->json(['message' => 'Check-out berhasil.', 'data' => $this->attendancePayload($record->fresh())]);
    }

    private function authorizeModule(Request $request, string $module, string $ability): Employee
    {
        /** @var Employee $employee */
        $employee = $request->user();
        abort_unless(CoreAccess::allows($employee, $module, $ability), 403, 'Fitur ini tidak tersedia untuk akun Core Anda.');

        return $employee;
    }

    private function authorizeModuleRecord(Request $request, string $module, string $ability): Employee
    {
        abort_unless($this->isModuleRecordBacked($module), 404, 'Modul ini belum tersedia untuk sinkronisasi record umum.');

        return $this->authorizeModule($request, HcisAccess::moduleRecordKey($module), $ability);
    }

    private function isModuleRecordBacked(string $module): bool
    {
        foreach (HcisAccess::routeModules() as $routeKey => $config) {
            if ($routeKey === $module || ($config['permission'] ?? null) === $module) {
                return true;
            }
        }

        return false;
    }

    private function moduleRecordWriteAbility(string $module): string
    {
        return match (HcisAccess::moduleRecordKey($module)) {
            'knowledge' => 'contribute',
            'learning' => 'consume',
            'performance' => 'update',
            default => 'create',
        };
    }

    private function userPayload(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'email' => $employee->email,
            'nrp' => $employee->nrp,
            'company' => $employee->company?->name,
            'company_id' => $employee->company_id,
            'department' => $employee->department?->name,
            'position' => $employee->position?->name,
            'workflow_role' => $employee->core_role,
            'access_roles' => $employee->coreAccessRoles->pluck('code')->values(),
        ];
    }

    private function attendancePayload(Attendance $attendance): array
    {
        return [
            'id' => $attendance->id,
            'date' => $attendance->date?->toDateString(),
            'check_in_at' => $attendance->check_in_at?->toIso8601String(),
            'check_out_at' => $attendance->check_out_at?->toIso8601String(),
            'status' => $attendance->status,
            'late_minutes' => $attendance->late_minutes,
            'updated_at' => $attendance->updated_at?->toIso8601String(),
        ];
    }

    private function leavePayload(LeaveRequest $leave): array
    {
        return [
            'id' => $leave->id,
            'leave_type' => $leave->leaveType ? [
                'id' => $leave->leaveType->id,
                'code' => $leave->leaveType->code,
                'name' => $leave->leaveType->name,
            ] : null,
            'start_date' => $leave->start_date?->toDateString(),
            'end_date' => $leave->end_date?->toDateString(),
            'days' => (float) $leave->days,
            'reason' => $leave->reason,
            'status' => $leave->status,
            'created_at' => $leave->created_at?->toIso8601String(),
            'updated_at' => $leave->updated_at?->toIso8601String(),
        ];
    }

    private function overtimePayload(OvertimeRequest $overtime): array
    {
        return [
            'id' => $overtime->id,
            'date' => $overtime->date?->toDateString(),
            'start_time' => substr((string) $overtime->start_time, 0, 5),
            'end_time' => substr((string) $overtime->end_time, 0, 5),
            'hours' => (float) $overtime->hours,
            'calculated_amount' => (float) $overtime->calculated_amount,
            'reason' => $overtime->reason,
            'status' => $overtime->status,
            'created_at' => $overtime->created_at?->toIso8601String(),
            'updated_at' => $overtime->updated_at?->toIso8601String(),
        ];
    }

    private function moduleRecordPayload(ModuleRecord $record): array
    {
        return [
            'id' => $record->id,
            'module' => $record->module,
            'reference_no' => $record->reference_no,
            'title' => $record->title,
            'employee' => $record->employee ? [
                'id' => $record->employee->id,
                'nrp' => $record->employee->nrp,
                'name' => $record->employee->full_name,
            ] : null,
            'company' => $record->company ? [
                'id' => $record->company->id,
                'code' => $record->company->code,
                'name' => $record->company->name,
            ] : null,
            'record_date' => $record->record_date?->toDateString(),
            'amount' => $record->amount !== null ? (float) $record->amount : null,
            'status' => $record->status,
            'details' => $record->details ?? [],
            'created_at' => $record->created_at?->toIso8601String(),
            'updated_at' => $record->updated_at?->toIso8601String(),
        ];
    }

    private function serviceRecords(Employee $employee, string $module): ?\Illuminate\Support\Collection
    {
        return match ($module) {
            'payroll' => DB::table('payslips')
                ->join('payroll_periods', 'payroll_periods.id', '=', 'payslips.payroll_period_id')
                ->where('payslips.employee_id', $employee->id)
                ->orderByDesc('payroll_periods.start_date')
                ->limit(12)
                ->get()
                ->map(fn ($row) => $this->normalizedRecord(
                    $row->id,
                    'payroll',
                    'Slip gaji '.$row->name,
                    $row->pay_date,
                    $row->net_amount,
                    $row->status,
                    [
                        'period' => $row->name,
                        'gross_amount' => (float) $row->gross_amount,
                        'deduction_amount' => (float) $row->deduction_amount,
                        'net_amount' => (float) $row->net_amount,
                    ]
                )),
            'claim' => DB::table('claims')
                ->where('employee_id', $employee->id)
                ->orderByDesc('claim_date')
                ->limit(50)
                ->get()
                ->map(fn ($row) => $this->normalizedRecord(
                    $row->id,
                    'claim',
                    $row->category,
                    $row->claim_date,
                    $row->amount,
                    $row->status,
                    ['description' => $row->description]
                )),
            'loan' => DB::table('loans')
                ->where('employee_id', $employee->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn ($row) => $this->normalizedRecord(
                    $row->id,
                    'loan',
                    $row->purpose,
                    $row->created_at,
                    $row->amount,
                    $row->status,
                    [
                        'tenor' => $row->tenor,
                        'installment_amount' => (float) $row->installment_amount,
                        'outstanding_amount' => (float) $row->outstanding_amount,
                    ]
                )),
            'document' => DB::table('employee_documents')
                ->where('employee_id', $employee->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn ($row) => $this->normalizedRecord(
                    $row->id,
                    'document',
                    $row->title,
                    $row->start_date ?? $row->created_at,
                    null,
                    $row->file_path ? 'available' : 'submitted',
                    ['description' => $row->description, 'type' => $row->type]
                )),
            'bpjs' => DB::table('employee_statutory_profiles')
                ->where('employee_id', $employee->id)
                ->orderByDesc('effective_date')
                ->limit(10)
                ->get()
                ->map(fn ($row) => $this->normalizedRecord(
                    $row->id,
                    'bpjs',
                    'Kepesertaan BPJS Kesehatan & Ketenagakerjaan',
                    $row->effective_date,
                    null,
                    $row->bpjs_health_active && $row->bpjs_employment_active ? 'active' : 'inactive',
                    [
                        'bpjs_health_number' => $row->bpjs_health_number,
                        'bpjs_employment_number' => $row->bpjs_employment_number,
                        'tax_status' => $row->tax_status,
                    ]
                )),
            'learning' => DB::table('learning_participants')
                ->join('learning_programs', 'learning_programs.id', '=', 'learning_participants.learning_program_id')
                ->where('learning_participants.employee_id', $employee->id)
                ->orderByDesc('learning_programs.start_date')
                ->limit(50)
                ->get()
                ->map(fn ($row) => $this->normalizedRecord(
                    $row->id,
                    'learning',
                    $row->name,
                    $row->start_date,
                    $row->actual_cost,
                    $row->status,
                    [
                        'provider' => $row->provider,
                        'score' => $row->score !== null ? (float) $row->score : null,
                        'passed' => $row->passed !== null ? (bool) $row->passed : null,
                        'certificate_number' => $row->certificate_number,
                    ]
                )),
            'performance' => DB::table('performance_reviews')
                ->join('performance_cycles', 'performance_cycles.id', '=', 'performance_reviews.performance_cycle_id')
                ->where('performance_reviews.employee_id', $employee->id)
                ->orderByDesc('performance_cycles.end_date')
                ->limit(20)
                ->get()
                ->map(fn ($row) => $this->normalizedRecord(
                    $row->id,
                    'performance',
                    $row->name,
                    $row->end_date,
                    null,
                    $row->status,
                    [
                        'final_score' => $row->final_score !== null ? (float) $row->final_score : null,
                        'rating' => $row->rating,
                        'strengths' => $row->strengths,
                        'development_areas' => $row->development_areas,
                    ]
                )),
            default => null,
        };
    }

    private function normalizedRecord(
        int $id,
        string $module,
        string $title,
        mixed $recordDate,
        mixed $amount,
        string $status,
        array $details = []
    ): array {
        return [
            'id' => $id,
            'module' => $module,
            'title' => $title,
            'record_date' => $recordDate ? Carbon::parse($recordDate)->toDateString() : null,
            'amount' => $amount !== null ? (float) $amount : null,
            'status' => $status,
            'details' => $details,
        ];
    }

    private function coreModuleKey(string $module): string
    {
        return match ($module) {
            'claims' => 'claim',
            'loans' => 'loan',
            'documents' => 'document',
            'benefit' => 'bpjs',
            default => HcisAccess::moduleRecordKey($module),
        };
    }

    private function recentActivities(Employee $employee): array
    {
        $records = collect();
        Attendance::where('employee_id', $employee->id)->latest('date')->limit(5)->get()->each(function ($row) use ($records) {
            $records->push([
                'id' => 'attendance-'.$row->id,
                'module' => 'attendance',
                'date' => $row->date?->toDateString(),
                'title' => 'Kehadiran '.($row->status === 'late' ? 'terlambat' : 'harian'),
                'detail' => trim(($row->check_in_at?->format('H:i') ?? 'Belum masuk').' - '.($row->check_out_at?->format('H:i') ?? 'Berjalan')),
                'status' => $row->status,
            ]);
        });
        LeaveRequest::with('leaveType')->where('employee_id', $employee->id)->latest()->limit(3)->get()->each(function ($row) use ($records) {
            $records->push([
                'id' => 'leave-'.$row->id,
                'module' => 'leave',
                'date' => $row->created_at?->toDateString(),
                'title' => $row->leaveType?->name ?? 'Pengajuan cuti',
                'detail' => $row->start_date?->format('d M').' - '.$row->end_date?->format('d M Y').' · '.(float) $row->days.' hari',
                'status' => $row->status,
            ]);
        });
        DB::table('claims')->where('employee_id', $employee->id)->latest('claim_date')->limit(3)->get()->each(function ($row) use ($records) {
            $records->push([
                'id' => 'claim-'.$row->id,
                'module' => 'claim',
                'date' => $row->claim_date,
                'title' => 'Reimbursement '.$row->category,
                'detail' => 'Rp'.number_format((float) $row->amount, 0, ',', '.'),
                'status' => $row->status,
            ]);
        });

        return $records->sortByDesc('date')->take(8)->values()->all();
    }

    private function pendingApprovalsFor(Employee $employee): \Illuminate\Support\Collection
    {
        return DB::table('approval_requests')
            ->where('company_id', $employee->company_id)
            ->whereIn('status', ['submitted', 'pending'])
            ->latest('submitted_at')
            ->limit(100)
            ->get()
            ->filter(function ($row) use ($employee) {
                $context = json_decode((string) $row->context, true) ?: [];

                return (int) ($context['manager_id'] ?? 0) === $employee->id
                    || ($employee->core_role === 'director' && (int) $row->company_id === $employee->company_id);
            })
            ->map(function ($row) {
                $context = json_decode((string) $row->context, true) ?: [];

                return [
                    'id' => $row->id,
                    'document_type' => $row->document_type,
                    'employee_name' => $context['employee_name'] ?? 'Karyawan',
                    'nrp' => $context['nrp'] ?? null,
                    'amount' => $row->amount !== null ? (float) $row->amount : null,
                    'status' => $row->status,
                    'submitted_at' => $row->submitted_at,
                    'channel' => $context['channel'] ?? 'hcis',
                ];
            });
    }

    private function coreNotifications(Employee $employee): array
    {
        if (! $employee->user) {
            return [];
        }

        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $employee->user->id)
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $data = json_decode((string) $row->data, true) ?: [];

                return [
                    'id' => $row->id,
                    'type' => $row->type,
                    'message' => $data['message'] ?? 'Pembaruan HCIS tersedia.',
                    'module' => $data['module'] ?? null,
                    'read_at' => $row->read_at,
                    'created_at' => $row->created_at,
                ];
            })
            ->all();
    }

    private function nextCoreReference(string $module): string
    {
        $prefix = 'COR-'.strtoupper(substr(HcisAccess::moduleRecordKey($module), 0, 3));

        do {
            $reference = $prefix.'-'.now()->format('YmdHis').'-'.random_int(100, 999);
        } while (ModuleRecord::where('reference_no', $reference)->exists());

        return $reference;
    }

    private function workingDays(int $companyId, Carbon $start, Carbon $end): int
    {
        $calendar = WorkCalendar::with('holidays')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();
        $workingDays = $calendar?->working_days ?: [1, 2, 3, 4, 5];
        $holidays = $calendar?->holidays->pluck('holiday_date')->map->toDateString()->all() ?: [];
        $count = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (in_array($date->dayOfWeekIso, $workingDays, true) && ! in_array($date->toDateString(), $holidays, true)) {
                $count++;
            }
        }

        return $count;
    }

    private function coreRequesterId(Employee $employee): int
    {
        if ($employee->user) {
            return $employee->user->id;
        }

        return User::firstOrCreate(
            ['email' => 'core.service@hcis.local'],
            [
                'name' => 'Core Service Account',
                'nrp' => 'CORE-SERVICE',
                'password' => Hash::make(Str::random(40)),
                'is_active' => false,
            ]
        )->id;
    }
}
