<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function run(PayrollPeriod $period): int
    {
        return DB::transaction(function () use ($period) {
            if ($period->status === 'finalized' || $period->locked_at) {
                throw \Illuminate\Validation\ValidationException::withMessages(['period' => 'Periode payroll sudah final dan terkunci.']);
            }
            $count = 0;
            Employee::where('status', 'active')->when($period->company_id, fn ($q) => $q->where('company_id', $period->company_id))->with('user')->chunk(100, function ($employees) use ($period, &$count) {
                foreach ($employees as $employee) {
                    $items = DB::table('employee_salaries')->join('salary_components', 'salary_components.id', '=', 'employee_salaries.salary_component_id')->where('employee_id', $employee->id)->whereDate('effective_date', '<=', $period->end_date)->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $period->start_date))->select('salary_components.id', 'salary_components.name', 'salary_components.type', 'employee_salaries.amount')->get();
                    $adjustments = DB::table('payroll_adjustments')->where('payroll_period_id', $period->id)->where('employee_id', $employee->id)->where('status', 'approved')->whereNull('deleted_at')->selectRaw('salary_component_id as id, name, type, amount')->get();
                    $items = $items->concat($adjustments);
                    $overtime = DB::table('overtime_requests')->where('employee_id', $employee->id)->where('status', 'approved')->whereBetween('date', [$period->start_date, $period->end_date])->sum('calculated_amount');
                    if ($overtime > 0) $items->push((object) ['id' => null, 'name' => 'Lembur', 'type' => 'earning', 'amount' => $overtime]);
                    $gross = $items->where('type', 'earning')->sum('amount');
                    $deductions = $items->where('type', 'deduction')->sum('amount');
                    $slip = $period->payslips()->updateOrCreate(['employee_id' => $employee->id], ['gross_amount' => $gross, 'deduction_amount' => $deductions, 'net_amount' => $gross - $deductions, 'status' => 'published']);
                    $slip->details()->delete();
                    foreach ($items as $item) {
                        $slip->details()->create(['salary_component_id' => $item->id, 'name' => $item->name, 'type' => $item->type, 'amount' => $item->amount]);
                    } $count++;
                }
            });
            $period->update(['status' => 'finalized', 'finalized_at' => now(), 'locked_at' => now(), 'locked_by' => auth()->id()]);

            return $count;
        });
    }
}
