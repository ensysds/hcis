<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeStatutoryProfile;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\StatutoryConfig;
use App\Support\CompanyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayrollSetupController extends Controller
{
    public function index(Request $request)
    {
        $this->allowAny($request, ['payroll.view', 'tax.view', 'bpjs.view']);
        $ids = CompanyAccess::ids($request->user());
        return view('enterprise.payroll-setup', [
            'companies' => Company::whereIn('id', $ids)->orderBy('name')->get(),
            'employees' => Employee::whereIn('company_id', $ids)->where('status', 'active')->orderBy('full_name')->get(),
            'periods' => PayrollPeriod::with('company')->whereIn('company_id', $ids)->latest()->get(),
            'components' => DB::table('salary_components')->where(fn ($q) => $q->whereNull('company_id')->orWhereIn('company_id', $ids))->orderBy('display_order')->get(),
            'configs' => StatutoryConfig::with('company')->whereIn('company_id', $ids)->latest('effective_date')->get(),
            'profiles' => EmployeeStatutoryProfile::with('employee.company')->whereHas('employee', fn ($q) => $q->whereIn('company_id', $ids))->latest('effective_date')->take(50)->get(),
            'adjustments' => PayrollAdjustment::with(['period.company', 'employee'])->whereHas('period', fn ($q) => $q->whereIn('company_id', $ids))->latest()->take(50)->get(),
        ]);
    }

    public function storeConfig(Request $request)
    {
        $data = $request->validate(['company_id' => ['required', 'exists:companies,id'], 'config_type' => ['required', Rule::in(['tax', 'bpjs_health', 'bpjs_employment', 'other'])], 'code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:100'], 'employee_rate' => ['required', 'numeric', 'min:0'], 'employer_rate' => ['required', 'numeric', 'min:0'], 'minimum_basis' => ['nullable', 'numeric', 'min:0'], 'maximum_basis' => ['nullable', 'numeric', 'gte:minimum_basis'], 'effective_date' => ['required', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:effective_date']]);
        $this->allowAny($request, match ($data['config_type']) {
            'tax' => ['tax.create', 'tax.update'],
            'bpjs_health', 'bpjs_employment' => ['bpjs.create', 'bpjs.update'],
            default => ['payroll.create', 'payroll.update'],
        });
        CompanyAccess::authorize($request->user(), $data['company_id']);
        StatutoryConfig::create($data + ['is_active' => true]);
        return back()->with('success', 'Konfigurasi statutory berhasil disimpan.');
    }

    public function storeProfile(Request $request)
    {
        $data = $request->validate(['employee_id' => ['required', 'exists:employees,id'], 'tax_status' => ['nullable', 'string', 'max:20'], 'tax_method' => ['required', Rule::in(['gross', 'gross_up', 'net'])], 'npwp' => ['nullable', 'string', 'max:50'], 'bpjs_health_number' => ['nullable', 'string', 'max:50'], 'bpjs_employment_number' => ['nullable', 'string', 'max:50'], 'bpjs_health_active' => ['nullable', 'boolean'], 'bpjs_employment_active' => ['nullable', 'boolean'], 'effective_date' => ['required', 'date']]);
        $this->allowAny($request, ['payroll.update', 'tax.update', 'bpjs.update']);
        $employee = Employee::findOrFail($data['employee_id']);
        CompanyAccess::authorize($request->user(), $employee->company_id);
        EmployeeStatutoryProfile::create($data + ['bpjs_health_active' => $request->boolean('bpjs_health_active'), 'bpjs_employment_active' => $request->boolean('bpjs_employment_active')]);
        return back()->with('success', 'Profil pajak dan BPJS karyawan berhasil disimpan.');
    }

    public function storeAdjustment(Request $request)
    {
        $this->allow($request, 'payroll.update');
        $data = $request->validate(['payroll_period_id' => ['required', 'exists:payroll_periods,id'], 'employee_id' => ['required', 'exists:employees,id'], 'salary_component_id' => ['nullable', 'exists:salary_components,id'], 'name' => ['required', 'string', 'max:100'], 'type' => ['required', Rule::in(['earning', 'deduction'])], 'amount' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'max:1000']]);
        $period = PayrollPeriod::findOrFail($data['payroll_period_id']);
        CompanyAccess::authorize($request->user(), $period->company_id);
        abort_unless($period->status === 'draft', 422, 'Adjustment hanya dapat ditambahkan pada periode draft.');
        abort_unless(Employee::whereKey($data['employee_id'])->where('company_id', $period->company_id)->exists(), 422, 'Karyawan tidak berasal dari perusahaan periode payroll.');
        PayrollAdjustment::create($data + ['reference_no' => 'ADJ-'.now()->format('YmdHis').'-'.random_int(100,999), 'status' => 'approved', 'created_by' => $request->user()->id]);
        return back()->with('success', 'Payroll adjustment berhasil ditambahkan.');
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
