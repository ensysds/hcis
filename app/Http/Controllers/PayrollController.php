<?php

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Support\CompanyAccess;

class PayrollController extends Controller
{
    public function index(Request $r)
    {
        abort_unless($this->canManagePayroll($r), 403);

        return view('payroll.index', ['periods' => PayrollPeriod::with('company')->withCount('payslips')->whereIn('company_id', CompanyAccess::ids($r->user()))->latest()->paginate(15), 'companies' => Company::whereIn('id', CompanyAccess::ids($r->user()))->orderBy('name')->get()]);
    }

    public function store(Request $r)
    {
        $data = $r->validate(['company_id' => 'required|exists:companies,id', 'name' => 'required', 'start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date', 'pay_date' => 'required|date']);
        CompanyAccess::authorize($r->user(), $data['company_id']);
        PayrollPeriod::create($data);

        return back()->with('success', 'Periode payroll dibuat.');
    }

    public function run(PayrollPeriod $period, PayrollService $service)
    {
        CompanyAccess::authorize(request()->user(), $period->company_id);
        $count = $service->run($period);

        return back()->with('success', "Payroll selesai untuk $count karyawan.");
    }

    public function pdf(Payslip $payslip, Request $r)
    {
        abort_unless($this->canManagePayroll($r), 403);
        $payslip->load(['employee', 'period', 'details']);
        CompanyAccess::authorize($r->user(), $payslip->employee->company_id);

        return Pdf::loadView('payroll.pdf', compact('payslip'))->download('slip-'.$payslip->employee->nrp.'-'.$payslip->period->name.'.pdf');
    }

    private function canManagePayroll(Request $request): bool
    {
        return $request->user()->hasRole('super_admin')
            || $request->user()->can('payroll.view')
            || $request->user()->can('payroll.create')
            || $request->user()->can('payroll.update')
            || $request->user()->can('payroll.delete');
    }
}
