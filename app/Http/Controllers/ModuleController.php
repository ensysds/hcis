<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\ModuleRecord;
use App\Support\CompanyAccess;
use App\Support\HcisAccess;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index(Request $r, string $module)
    {
        $config = $this->guard($module, $r);
        $companyIds = CompanyAccess::ids($r->user());
        $q = ModuleRecord::with(['company', 'employee'])
            ->whereIn('module', HcisAccess::moduleRecordAliases($module))
            ->whereIn('company_id', $companyIds)
            ->latest();

        return view('modules.index', [
            'module' => $module,
            'title' => $config['label'],
            'permissionKey' => $config['permission'],
            'records' => $q->paginate(15),
            'companies' => Company::whereIn('id', $companyIds)->orderBy('name')->get(),
            'employees' => Employee::whereIn('company_id', $companyIds)->orderBy('full_name')->get(),
        ]);
    }

    public function store(Request $r, string $module)
    {
        $this->guard($module, $r, 'create');
        $data = $r->validate(['company_id' => 'nullable|exists:companies,id', 'title' => 'required|string|max:200', 'employee_id' => 'nullable|exists:employees,id', 'record_date' => 'nullable|date', 'amount' => 'nullable|numeric|min:0', 'status' => 'required|in:draft,submitted,in_review,approved,rejected,completed,active,inactive', 'description' => 'nullable|string|max:2000']);
        if (! empty($data['employee_id'])) {
            $employee = Employee::findOrFail($data['employee_id']);
            CompanyAccess::authorize($r->user(), $employee->company_id);
            $data['company_id'] = $employee->company_id;
        } else {
            $data['company_id'] = $data['company_id'] ?? CompanyAccess::ids($r->user())->first();
            CompanyAccess::authorize($r->user(), $data['company_id']);
        }
        $data['module'] = HcisAccess::moduleRecordKey($module);
        $data['reference_no'] = strtoupper(substr($data['module'], 0, 3)).'-'.now()->format('YmdHis').'-'.random_int(10, 99);
        $data['details'] = ['description' => $data['description'] ?? null];
        unset($data['description']);
        $data['created_by'] = $r->user()->id;
        ModuleRecord::create($data);

        return back()->with('success', 'Data berhasil disimpan.');
    }

    public function destroy(string $module, ModuleRecord $record, Request $r)
    {
        $this->guard($module, $r, 'delete');
        abort_unless(in_array($record->module, HcisAccess::moduleRecordAliases($module), true), 404);
        CompanyAccess::authorize($r->user(), $record->company_id);
        $record->delete();

        return back()->with('success', 'Data diarsipkan.');
    }

    private function guard(string $module, Request $request, string $action = 'view'): array
    {
        $config = HcisAccess::routeModule($module);
        abort_unless($config, 404);
        abort_unless($request->user()->hasRole('super_admin') || $request->user()->can("{$config['permission']}.{$action}"), 403);

        return $config;
    }
}
