<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\OvertimePolicy;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Support\CompanyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TimeAdministrationController extends Controller
{
    public function index(Request $request)
    {
        $this->allowAny($request, ['shift.view', 'attendance.view', 'leave.view', 'overtime.view']);
        $ids = CompanyAccess::ids($request->user());

        return view('enterprise.time', [
            'companies' => Company::whereIn('id', $ids)->where('is_active', true)->orderBy('name')->get(),
            'employees' => Employee::whereIn('company_id', $ids)->where('status', 'active')->orderBy('full_name')->get(),
            'shifts' => Shift::with('company')->where(fn ($q) => $q->whereNull('company_id')->orWhereIn('company_id', $ids))->orderBy('name')->get(),
            'schedules' => ShiftSchedule::with(['employee.company', 'shift'])->whereHas('employee', fn ($q) => $q->whereIn('company_id', $ids))->whereBetween('date', [$request->date_from ?: today()->startOfMonth(), $request->date_to ?: today()->endOfMonth()])->orderBy('date')->paginate(20),
            'corrections' => AttendanceCorrection::with(['employee', 'attendance'])->whereHas('employee', fn ($q) => $q->whereIn('company_id', $ids))->latest()->take(30)->get(),
            'leaveTypes' => LeaveType::where(fn ($q) => $q->whereNull('company_id')->orWhereIn('company_id', $ids))->orderBy('name')->get(),
            'overtimePolicies' => OvertimePolicy::with('company')->whereIn('company_id', $ids)->get(),
            'attendances' => Attendance::with('employee')->whereHas('employee', fn ($q) => $q->whereIn('company_id', $ids))->latest('date')->take(100)->get(),
        ]);
    }

    public function storeShift(Request $request)
    {
        $this->allow($request, 'shift.create');
        $data = $request->validate(['company_id' => ['required', 'exists:companies,id'], 'code' => ['required', 'string', 'max:30', 'unique:shifts,code'], 'name' => ['required', 'string', 'max:100'], 'start_time' => ['required'], 'end_time' => ['required'], 'late_tolerance_minutes' => ['required', 'integer', 'min:0'], 'break_minutes' => ['required', 'integer', 'min:0'], 'crosses_midnight' => ['nullable', 'boolean']]);
        CompanyAccess::authorize($request->user(), $data['company_id']);
        Shift::create($data + ['crosses_midnight' => $request->boolean('crosses_midnight'), 'is_active' => true]);

        return back()->with('success', 'Shift berhasil dibuat.');
    }

    public function storeSchedule(Request $request)
    {
        $this->allow($request, 'shift.update');
        $data = $request->validate(['employee_ids' => ['required', 'array', 'min:1'], 'employee_ids.*' => ['exists:employees,id'], 'shift_id' => ['required', 'exists:shifts,id'], 'date_from' => ['required', 'date'], 'date_to' => ['required', 'date', 'after_or_equal:date_from']]);
        $shift = Shift::findOrFail($data['shift_id']);
        if ($shift->company_id) CompanyAccess::authorize($request->user(), $shift->company_id);
        $employees = Employee::whereIn('id', $data['employee_ids'])->get();
        foreach ($employees as $employee) {
            CompanyAccess::authorize($request->user(), $employee->company_id);
            abort_if($shift->company_id && $shift->company_id !== $employee->company_id, 422, 'Shift dan karyawan harus berasal dari perusahaan yang sama.');
        }
        $start = \Carbon\Carbon::parse($data['date_from']);
        $end = \Carbon\Carbon::parse($data['date_to']);
        DB::transaction(function () use ($employees, $shift, $start, $end) {
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                foreach ($employees as $employee) ShiftSchedule::updateOrCreate(['employee_id' => $employee->id, 'date' => $date->toDateString()], ['shift_id' => $shift->id]);
            }
        });

        return back()->with('success', 'Roster berhasil dibuat untuk rentang tanggal terpilih.');
    }

    public function storeCorrection(Request $request)
    {
        $this->allow($request, 'attendance.update');
        $data = $request->validate(['attendance_id' => ['required', 'exists:attendances,id'], 'requested_in' => ['nullable', 'date'], 'requested_out' => ['nullable', 'date', 'after:requested_in'], 'reason' => ['required', 'string', 'max:1000']]);
        $attendance = Attendance::with('employee')->findOrFail($data['attendance_id']);
        CompanyAccess::authorize($request->user(), $attendance->employee->company_id);
        AttendanceCorrection::create($data + ['employee_id' => $attendance->employee_id, 'status' => 'submitted']);

        return back()->with('success', 'Koreksi kehadiran diajukan.');
    }

    public function approveCorrection(Request $request, AttendanceCorrection $correction)
    {
        $this->allow($request, 'attendance.approve');
        CompanyAccess::authorize($request->user(), $correction->employee->company_id);
        $data = $request->validate(['action' => ['required', Rule::in(['approved', 'rejected'])], 'approval_notes' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($correction, $data, $request) {
            $correction->update(['status' => $data['action'], 'approval_notes' => $data['approval_notes'] ?? null, 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            if ($data['action'] === 'approved') {
                $correction->attendance->update(array_filter(['check_in_at' => $correction->requested_in, 'check_out_at' => $correction->requested_out]));
            }
        });

        return back()->with('success', 'Keputusan koreksi kehadiran disimpan.');
    }

    public function storeLeaveType(Request $request)
    {
        $this->allowAny($request, ['leave.create', 'leave.update']);
        $data = $request->validate(['company_id' => ['nullable', 'exists:companies,id'], 'code' => ['required', 'string', 'max:30', 'unique:leave_types,code'], 'name' => ['required', 'string', 'max:100'], 'annual_quota' => ['required', 'numeric', 'min:0'], 'minimum_notice_days' => ['required', 'integer', 'min:0'], 'maximum_consecutive_days' => ['nullable', 'integer', 'min:1'], 'carry_forward_limit' => ['required', 'numeric', 'min:0'], 'requires_attachment' => ['nullable', 'boolean'], 'deduct_balance' => ['nullable', 'boolean'], 'allow_half_day' => ['nullable', 'boolean']]);
        if ($data['company_id'] ?? null) CompanyAccess::authorize($request->user(), $data['company_id']);
        LeaveType::create($data + ['requires_attachment' => $request->boolean('requires_attachment'), 'deduct_balance' => $request->boolean('deduct_balance'), 'allow_half_day' => $request->boolean('allow_half_day'), 'is_active' => true]);

        return back()->with('success', 'Kebijakan jenis cuti berhasil dibuat.');
    }

    public function storeOvertimePolicy(Request $request)
    {
        $this->allowAny($request, ['overtime.create', 'overtime.update']);
        $data = $request->validate(['company_id' => ['required', 'exists:companies,id'], 'name' => ['required', 'string', 'max:100'], 'weekday_multiplier' => ['required', 'numeric', 'min:0'], 'weekend_multiplier' => ['required', 'numeric', 'min:0'], 'holiday_multiplier' => ['required', 'numeric', 'min:0'], 'minimum_hours' => ['required', 'numeric', 'min:0'], 'maximum_hours_per_day' => ['nullable', 'numeric', 'gt:minimum_hours'], 'monthly_divisor' => ['required', 'integer', 'min:1']]);
        CompanyAccess::authorize($request->user(), $data['company_id']);
        OvertimePolicy::create($data + ['is_active' => true]);

        return back()->with('success', 'Kebijakan lembur berhasil dibuat.');
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasRole('super_admin') || $request->user()->can($permission), 403);
    }

    private function allowAny(Request $request, array $permissions): void
    {
        abort_unless($request->user()->hasRole('super_admin') || collect($permissions)->contains(fn ($permission) => $request->user()->can($permission)), 403);
    }
}
