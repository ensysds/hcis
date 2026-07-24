<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CoreAccessRole;
use App\Models\Employee;
use App\Models\SolutionModule;
use App\Support\CompanyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CoreAccessController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::whereIn('id', CompanyAccess::ids($request->user()))->orderBy('name')->get();
        $company = $companies->firstWhere('id', (int) $request->integer('company_id')) ?? $companies->first();

        return view('core-access.index', [
            'companies' => $companies,
            'company' => $company,
            'modules' => SolutionModule::where('solution', 'hcis')->orderBy('sort_order')->get(),
            'activeModuleIds' => $company?->solutionModules()->wherePivot('is_active', true)->pluck('solution_modules.id')->all() ?? [],
            'roles' => $company?->coreAccessRoles()->with('modules')->orderBy('name')->get() ?? collect(),
            'employees' => $company?->employees()->with('coreAccessRoles')->where('status', 'active')->orderBy('full_name')->get() ?? collect(),
        ]);
    }

    public function updateCompanyModules(Request $request, Company $company)
    {
        CompanyAccess::authorize($request->user(), $company->id);
        $data = $request->validate(['modules' => ['array'], 'modules.*' => ['integer', 'exists:solution_modules,id']]);
        $enabled = collect($data['modules'] ?? [])->map(fn ($id) => (int) $id);

        SolutionModule::where('solution', 'hcis')->get()->each(function (SolutionModule $module) use ($company, $enabled) {
            $company->solutionModules()->syncWithoutDetaching([
                $module->id => ['is_active' => $enabled->contains($module->id)],
            ]);
        });

        return back()->with('success', 'Modul HCIS untuk perusahaan berhasil diperbarui.');
    }

    public function storeRole(Request $request)
    {
        $companyIds = CompanyAccess::ids($request->user());
        $data = $request->validate([
            'company_id' => ['required', Rule::in($companyIds->all())],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:80'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['integer', 'exists:solution_modules,id'],
        ]);
        $code = Str::upper($data['code'] ?: Str::slug($data['name'], '-'));
        $role = CoreAccessRole::updateOrCreate(
            ['company_id' => $data['company_id'], 'code' => $code],
            ['name' => $data['name'], 'description' => 'Custom Core access role', 'is_active' => true]
        );
        $allowedModules = Company::findOrFail($data['company_id'])->solutionModules()
            ->wherePivot('is_active', true)
            ->whereIn('solution_modules.id', $data['modules'])
            ->pluck('solution_modules.id');
        $role->modules()->sync($allowedModules->mapWithKeys(fn ($id) => [$id => ['abilities' => json_encode(['view'])]])->all());

        return back()->with('success', 'Role Core berhasil disimpan.');
    }

    public function updateEmployeeRoles(Request $request, Employee $employee)
    {
        CompanyAccess::authorize($request->user(), $employee->company_id);
        $data = $request->validate(['roles' => ['array'], 'roles.*' => ['integer', 'exists:core_access_roles,id']]);
        $roleIds = CoreAccessRole::where('company_id', $employee->company_id)
            ->whereIn('id', $data['roles'] ?? [])
            ->pluck('id');
        $employee->coreAccessRoles()->sync($roleIds->mapWithKeys(fn ($id) => [$id => ['scope_type' => 'self']])->all());

        return back()->with('success', 'Akses Core karyawan berhasil diperbarui.');
    }
}
