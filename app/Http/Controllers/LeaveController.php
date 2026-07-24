<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\ApprovalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Support\CompanyAccess;
use App\Models\WorkCalendar;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class LeaveController extends Controller
{
    public function index(Request $r)
    {
        $q = LeaveRequest::with(['employee', 'leaveType'])->whereHas('employee', fn ($query) => $query->whereIn('company_id', CompanyAccess::ids($r->user())))->latest();

        return view('leave.index', ['requests' => $q->paginate(15)]);
    }

    public function create(Request $request)
    {
        return view('leave.form', [
            'types' => LeaveType::orderBy('name')->get(),
            'employees' => Employee::whereIn('company_id', CompanyAccess::ids($request->user()))->where('status', 'active')->orderBy('full_name')->get()
                ->mapWithKeys(fn (Employee $employee) => [$employee->id => $employee->nrp.' - '.$employee->full_name]),
        ]);
    }

    public function store(Request $r, ApprovalService $approval)
    {
        $data = $r->validate(['employee_id' => 'required|exists:employees,id', 'leave_type_id' => 'required|exists:leave_types,id', 'start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date', 'reason' => 'required|string|max:1000', 'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048']);
        $employee = Employee::findOrFail($data['employee_id']);
        CompanyAccess::authorize($r->user(), $employee->company_id);
        $type = LeaveType::whereKey($data['leave_type_id'])->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $employee->company_id))->firstOrFail();
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        if ($type->requires_attachment && ! $r->hasFile('attachment')) throw ValidationException::withMessages(['attachment' => 'Lampiran wajib untuk jenis cuti ini.']);
        if ($type->minimum_notice_days && today()->diffInDays($start, false) < $type->minimum_notice_days) throw ValidationException::withMessages(['start_date' => 'Pengajuan membutuhkan pemberitahuan minimal '.$type->minimum_notice_days.' hari.']);
        $days = $this->workingDays($employee->company_id, $start, $end);
        if ($days <= 0) throw ValidationException::withMessages(['start_date' => 'Rentang tidak memiliki hari kerja.']);
        if ($type->maximum_consecutive_days && $days > $type->maximum_consecutive_days) throw ValidationException::withMessages(['end_date' => 'Maksimum cuti berurutan adalah '.$type->maximum_consecutive_days.' hari kerja.']);
        $overlap = LeaveRequest::where('employee_id', $employee->id)->whereNotIn('status', ['rejected', 'cancelled'])->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->exists();
        if ($overlap) throw ValidationException::withMessages(['start_date' => 'Karyawan memiliki pengajuan cuti yang bertumpang tindih.']);
        unset($data['attachment']);
        $leave = LeaveRequest::create($data + ['days' => $days, 'created_by' => $r->user()->id, 'updated_by' => $r->user()->id, 'status' => 'draft']);
        if ($r->hasFile('attachment')) {
            $leave->update(['attachment_path' => $r->file('attachment')->store('leave', 'local')]);
        }
        $approval->submit($leave, 'leave', $r->user()->id);

        return redirect()->route('leave.index')->with('success', 'Data cuti karyawan dikirim untuk proses internal HC.');
    }

    private function workingDays(int $companyId, Carbon $start, Carbon $end): int
    {
        $calendar = WorkCalendar::with('holidays')->where('company_id', $companyId)->where('is_active', true)->orderByDesc('is_default')->first();
        $workingDays = $calendar?->working_days ?: [1, 2, 3, 4, 5];
        $holidays = $calendar?->holidays->pluck('holiday_date')->map->toDateString()->all() ?: [];
        $count = 0;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (in_array($date->dayOfWeekIso, $workingDays, true) && ! in_array($date->toDateString(), $holidays, true)) $count++;
        }
        return $count;
    }

    public function cancel(LeaveRequest $leaveRequest, Request $r)
    {
        CompanyAccess::authorize($r->user(), $leaveRequest->employee->company_id);
        abort_unless(in_array($leaveRequest->status, ['draft', 'submitted']), 422);
        $leaveRequest->update(['status' => 'cancelled']);
        $leaveRequest->approval?->update(['status' => 'cancelled', 'completed_at' => now()]);

        return back()->with('success', 'Data cuti dibatalkan.');
    }

    public function attachment(LeaveRequest $leaveRequest, Request $request)
    {
        CompanyAccess::authorize($request->user(), $leaveRequest->employee->company_id);
        abort_unless($leaveRequest->attachment_path && Storage::disk('local')->exists($leaveRequest->attachment_path), 404);

        return Storage::disk('local')->download($leaveRequest->attachment_path);
    }
}
