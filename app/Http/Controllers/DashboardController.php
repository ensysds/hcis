<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Support\CompanyAccess;

class DashboardController extends Controller
{
    public function ess()
    {
        return view('dashboard.ess');
    }

    public function admin()
    {
        $ids = CompanyAccess::ids(request()->user());
        return view('dashboard.admin', ['headcount' => Employee::whereIn('company_id', $ids)->where('status', 'active')->count(), 'present' => Attendance::whereHas('employee', fn ($q) => $q->whereIn('company_id', $ids))->whereDate('date', today())->whereNotNull('check_in_at')->count(), 'pending' => ApprovalRequest::whereIn('company_id', $ids)->whereIn('status', ['submitted', 'in_review'])->count(), 'periods' => PayrollPeriod::whereIn('company_id', $ids)->latest()->take(5)->get(), 'departments' => Employee::join('departments', 'departments.id', '=', 'employees.department_id')->whereIn('employees.company_id', $ids)->selectRaw('departments.name, count(*) total')->groupBy('departments.name')->pluck('total', 'name')]);
    }
}
