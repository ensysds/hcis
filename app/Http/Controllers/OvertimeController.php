<?php

namespace App\Http\Controllers;

use App\Models\OvertimeRequest;
use App\Models\Employee;
use App\Services\ApprovalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Support\CompanyAccess;
use App\Models\OvertimePolicy;
use App\Models\WorkCalendar;
use Illuminate\Support\Facades\DB;

class OvertimeController extends Controller
{
    public function index(Request $r)
    {
        $q = OvertimeRequest::with('employee')->whereHas('employee', fn ($query) => $query->whereIn('company_id', CompanyAccess::ids($r->user())))->latest();

        return view('overtime.index', ['requests' => $q->paginate(15)]);
    }

    public function create(Request $request)
    {
        return view('overtime.form', [
            'employees' => Employee::whereIn('company_id', CompanyAccess::ids($request->user()))->where('status', 'active')->orderBy('full_name')->get()
                ->mapWithKeys(fn (Employee $employee) => [$employee->id => $employee->nrp.' - '.$employee->full_name]),
        ]);
    }

    public function store(Request $r, ApprovalService $approval)
    {
        $data = $r->validate(['employee_id' => 'required|exists:employees,id', 'date' => 'required|date', 'start_time' => 'required', 'end_time' => 'required|after:start_time', 'reason' => 'required|string|max:1000']);
        $employee = Employee::findOrFail($data['employee_id']);
        CompanyAccess::authorize($r->user(), $employee->company_id);
        $hours = Carbon::parse($data['start_time'])->diffInMinutes(Carbon::parse($data['end_time'])) / 60;
        $policy = OvertimePolicy::where('company_id', $employee->company_id)->where('is_active', true)->latest()->first();
        if ($policy && $policy->maximum_hours_per_day && $hours > $policy->maximum_hours_per_day) abort(422, 'Durasi melebihi batas kebijakan lembur.');
        $amount = 0;
        if ($policy && $hours >= $policy->minimum_hours) {
            $date = Carbon::parse($data['date']);
            $holiday = WorkCalendar::where('company_id', $employee->company_id)->whereHas('holidays', fn ($q) => $q->whereDate('holiday_date', $date))->exists();
            $multiplier = $holiday ? $policy->holiday_multiplier : ($date->isWeekend() ? $policy->weekend_multiplier : $policy->weekday_multiplier);
            $basic = DB::table('employee_salaries')->join('salary_components', 'salary_components.id', '=', 'employee_salaries.salary_component_id')->where('employee_id', $employee->id)->where('salary_components.code', 'BASIC')->whereDate('effective_date', '<=', $date)->orderByDesc('effective_date')->value('amount') ?: 0;
            $amount = round(($basic / $policy->monthly_divisor) * $hours * $multiplier, 2);
        }
        $item = OvertimeRequest::create($data + ['hours' => $hours, 'overtime_policy_id' => $policy?->id, 'calculated_amount' => $amount, 'status' => 'draft', 'created_by' => $r->user()->id]);
        $approval->submit($item, 'overtime', $r->user()->id);

        return redirect()->route('overtime.index')->with('success', 'Data lembur karyawan dikirim untuk proses internal HC.');
    }
}
