<?php

namespace App\Http\Controllers;

use App\Exports\EmployeesExport;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Support\CompanyAccess;

class ReportController extends Controller
{
    public function employeesExcel()
    {
        return Excel::download(new EmployeesExport(CompanyAccess::ids(request()->user())->all()), 'employee-report.xlsx');
    }

    public function employeesPdf()
    {
        return Pdf::loadView('reports.employees', ['employees' => Employee::with(['company', 'department', 'position'])->whereIn('company_id', CompanyAccess::ids(request()->user()))->orderBy('nrp')->get()])->setPaper('a4', 'landscape')->download('employee-report.pdf');
    }
}
