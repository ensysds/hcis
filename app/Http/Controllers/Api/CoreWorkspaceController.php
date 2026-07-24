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
            ->where('year', now()->year)
            ->selectRaw('coalesce(sum(entitled - used), 0) as remaining')
            ->value('remaining');
        $latestPayslip = Payslip::with('period')
            ->where('employee_id', $employee->id)
            ->where('status', 'published')
            ->latest('id')
            ->first();

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
            ],
            'updated_at' => now()->toIso8601String(),
            'version' => 1,
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
        $employee = $this->authorizeModuleRecord($request, $module, 'view');
        $records = ModuleRecord::with(['company', 'employee'])
            ->where('employee_id', $employee->id)
            ->whereIn('module', HcisAccess::moduleRecordAliases($module))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ModuleRecord $record) => $this->moduleRecordPayload($record));

        return response()->json(['data' => $records, 'updated_at' => now()->toIso8601String(), 'version' => 1]);
    }

    public function storeModuleRecord(Request $request, string $module): JsonResponse
    {
        $employee = $this->authorizeModuleRecord($request, $module, $this->moduleRecordWriteAbility($module));
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'record_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['draft', 'submitted', 'in_review', 'approved', 'rejected', 'completed', 'active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'external_id' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ]);

        $record = ModuleRecord::create([
            'company_id' => $employee->company_id,
            'module' => HcisAccess::moduleRecordKey($module),
            'reference_no' => $this->nextCoreReference($module),
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
        activity('core-sync')->performedOn($record)->withProperties(['module' => $module, 'channel' => 'core'])->log('Record modul dibuat dari Core');

        return response()->json(['message' => 'Data Core berhasil disinkronkan ke HCIS.', 'data' => $this->moduleRecordPayload($record->fresh(['company', 'employee']))], 201);
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
