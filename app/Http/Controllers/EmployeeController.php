<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CoreApiToken;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Support\CompanyAccess;
use App\Support\EmployeeProfile;
use App\Support\FixedSuperadmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    public function index(Request $r)
    {
        $employees = Employee::with(['company', 'department', 'position'])->whereIn('company_id', CompanyAccess::ids($r->user()))->when($r->q, fn ($q, $v) => $q->where(fn ($x) => $x->where('full_name', 'like', "%$v%")->orWhere('nrp', 'like', "%$v%")->orWhere('nik', 'like', "%$v%")->orWhere('email', 'like', "%$v%")->orWhere('pt', 'like', "%$v%")))->when($r->department_id, fn ($q, $v) => $q->where('department_id', $v))->paginate(15)->withQueryString();

        return view('employees.index', compact('employees') + ['departments' => Department::whereIn('company_id', CompanyAccess::ids($r->user()))->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('employees.form', $this->formData(new Employee));
    }

    public function store(Request $r)
    {
        $payload = $this->validated($r);
        $children = $payload['children'] ?? [];
        unset($payload['children']);
        $payload = $this->preparePayload($payload);

        $employee = DB::transaction(function () use ($payload, $children, $r) {
            $employee = Employee::create($payload + ['created_by' => $r->user()->id, 'updated_by' => $r->user()->id]);
            $this->syncChildren($employee, $children);

            return $employee;
        });
        activity()->performedOn($employee)->causedBy($r->user())->log('Karyawan dibuat');

        return redirect()->route('employees.show', $employee)->with('success', 'Karyawan berhasil dibuat.');
    }

    public function show(Employee $employee)
    {
        CompanyAccess::authorize(request()->user(), $employee->company_id);
        $employee->load(['company', 'department', 'position', 'manager', 'user', 'children']);

        return view('employees.show', compact('employee') + ['sections' => EmployeeProfile::sections(), 'coreRoles' => $this->coreRoleOptions()]);
    }

    public function edit(Employee $employee)
    {
        CompanyAccess::authorize(request()->user(), $employee->company_id);
        return view('employees.form', $this->formData($employee));
    }

    public function update(Request $r, Employee $employee)
    {
        CompanyAccess::authorize($r->user(), $employee->company_id);
        $payload = $this->validated($r);
        $children = $payload['children'] ?? [];
        unset($payload['children']);
        $payload = $this->preparePayload($payload);

        DB::transaction(function () use ($employee, $payload, $children, $r) {
            $employee->update($payload + ['updated_by' => $r->user()->id]);
            $this->syncChildren($employee, $children);
        });
        if (! $employee->fresh()->canLoginToCore()) {
            CoreApiToken::where('employee_id', $employee->id)->delete();
        }

        return redirect()->route('employees.show', $employee)->with('success', 'Data karyawan diperbarui.');
    }

    public function destroy(Employee $employee)
    {
        abort_unless(request()->user()->hasRole('super_admin'), 403, 'Hanya superadmin yang boleh menghapus data karyawan.');
        CompanyAccess::authorize(request()->user(), $employee->company_id);
        CoreApiToken::where('employee_id', $employee->id)->delete();
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Karyawan diarsipkan.');
    }

    public function requestCorePasswordReset(Request $request, Employee $employee)
    {
        CompanyAccess::authorize($request->user(), $employee->company_id);
        abort_unless($request->user()->can('employee.update') || $request->user()->hasRole('super_admin'), 403);

        $employee->update(['core_password_reset_requested_at' => now()]);

        return back()->with('success', 'Permintaan reset password Core sudah dikirim ke superadmin.');
    }

    public function resetCorePassword(Request $request, Employee $employee)
    {
        abort_unless($request->user()->hasRole('super_admin'), 403, 'Hanya superadmin yang boleh reset password Core.');
        CompanyAccess::authorize($request->user(), $employee->company_id);

        $employee->resetCorePasswordToDefault();
        CoreApiToken::where('employee_id', $employee->id)->delete();

        return back()->with('success', 'Password Core direset ke default dan sesi Core aktif dicabut.');
    }

    public function updateCorePassword(Request $request, Employee $employee)
    {
        abort_unless($request->user()->hasRole('super_admin'), 403, 'Hanya superadmin yang boleh mengganti password Core.');
        CompanyAccess::authorize($request->user(), $employee->company_id);

        $data = $request->validate([
            'core_password' => ['required', 'string', 'min:8', 'max:190', 'confirmed'],
        ]);
        $password = FixedSuperadmin::isEmail($employee->email) ? FixedSuperadmin::PASSWORD : $data['core_password'];

        $employee->forceFill([
            'core_password' => Hash::make($password),
            'core_must_change_password' => false,
            'core_password_reset_requested_at' => null,
        ])->save();
        CoreApiToken::where('employee_id', $employee->id)->delete();

        return back()
            ->with('success', 'Password Core berhasil diganti dan sesi Core aktif dicabut.')
            ->with('core_password_updated_employee_id', $employee->id);
    }

    public function me()
    {
        return view('employees.show', [
            'employee' => auth()->user()->employee?->load(['company', 'department', 'position', 'manager', 'user', 'children']),
            'sections' => EmployeeProfile::sections(),
            'coreRoles' => $this->coreRoleOptions(),
        ]);
    }

    private function validated(Request $r)
    {
        $payload = $r->validate(EmployeeProfile::rules($r->route('employee')?->id));
        $payload += $r->validate([
            'core_role' => ['required', Rule::in(array_keys($this->coreRoleOptions()))],
            'core_is_active' => ['nullable', 'boolean'],
        ]);
        $payload['core_is_active'] = $r->boolean('core_is_active');

        if (! Company::whereKey($payload['company_id'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['company_id' => 'Perusahaan harus aktif dan dipilih dari Company Management.']);
        }
        CompanyAccess::authorize($r->user(), $payload['company_id']);
        if (! empty($payload['department_id']) && ! Department::whereKey($payload['department_id'])->where('company_id', $payload['company_id'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['department_id' => 'Unit organisasi harus aktif dan berasal dari perusahaan yang dipilih.']);
        }
        if (! empty($payload['position_id'])) {
            $validPosition = Position::whereKey($payload['position_id'])
                ->whereHas('department', fn ($query) => $query->where('company_id', $payload['company_id'])->when($payload['department_id'] ?? null, fn ($q, $id) => $q->whereKey($id)))
                ->exists();
            if (! $validPosition) {
                throw ValidationException::withMessages(['position_id' => 'Posisi harus berasal dari unit organisasi dan perusahaan yang dipilih.']);
            }
        }

        return $payload;
    }

    private function formData(Employee $employee)
    {
        $employee->loadMissing('children');
        $suggestions = [];
        foreach (EmployeeProfile::suggestionFields() as $field) {
            $suggestions[$field] = Employee::whereIn('company_id', CompanyAccess::ids(request()->user()))->whereNotNull($field)->where($field, '!=', '')->distinct()->orderBy($field)->pluck($field);
        }
        return compact('employee', 'suggestions') + [
            'sections' => EmployeeProfile::sections(),
            'coreRoles' => $this->coreRoleOptions(),
            'sources' => [
                'companies' => Company::whereIn('id', CompanyAccess::ids(request()->user()))->where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($company) => [$company->id => $company->code.' · '.$company->name]),
                'departments' => Department::with('company')->whereIn('company_id', CompanyAccess::ids(request()->user()))->where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($department) => [$department->id => $department->company->code.' · '.$department->name]),
                'positions' => Position::with('department.company')->whereHas('department', fn ($q) => $q->whereIn('company_id', CompanyAccess::ids(request()->user())))->orderBy('name')->get()->mapWithKeys(fn ($position) => [$position->id => $position->department->company->code.' · '.$position->department->name.' · '.$position->name]),
                'managers' => Employee::whereIn('company_id', CompanyAccess::ids(request()->user()))->when($employee->exists, fn ($query) => $query->whereKeyNot($employee->id))->orderBy('full_name')->get()->mapWithKeys(fn ($manager) => [$manager->id => $manager->nrp.' · '.$manager->full_name]),
            ],
        ];
    }

    private function preparePayload(array $payload): array
    {
        $payload['end_date'] = $payload['exit_date'] ?? null;
        if (($payload['status'] ?? null) !== 'active') {
            $payload['core_is_active'] = false;
        }

        return $payload;
    }

    private function coreRoleOptions(): array
    {
        return [
            'employee' => 'Employee',
            'manager' => 'Manager',
            'general_manager' => 'General Manager',
            'director' => 'Director',
        ];
    }

    private function syncChildren(Employee $employee, array $children): void
    {
        $employee->children()->delete();
        foreach (array_slice($children, 0, 7) as $index => $child) {
            if (blank($child['name'] ?? null)) {
                continue;
            }
            $employee->children()->create([
                'sequence' => $index + 1,
                'name' => $child['name'],
                'gender' => $child['gender'] ?? null,
                'birth_date' => $child['birth_date'] ?? null,
                'birth_place' => $child['birth_place'] ?? null,
            ]);
        }
    }
}
