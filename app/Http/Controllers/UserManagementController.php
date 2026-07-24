<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Support\CompanyAccess;
use App\Support\HcisAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        HcisAccess::ensurePermissions();

        $companyIds = CompanyAccess::ids($request->user());
        $users = User::with(['companies', 'roles'])
            ->when(! $request->user()->hasRole('super_admin'), function ($query) use ($companyIds) {
                $query->where(function ($inner) use ($companyIds) {
                    $inner->whereHas('companies', fn ($company) => $company->whereIn('companies.id', $companyIds));
                })->whereDoesntHave('roles', fn ($role) => $role->where('name', 'super_admin'));
            })
            ->when($request->q, function ($query, $search) {
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nrp', 'like', "%{$search}%"));
            })
            ->when($request->role, fn ($query, $role) => $query->role($role))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'roles'));
    }

    public function create(Request $request)
    {
        HcisAccess::ensureDefaultModuleRoles();

        return view('users.form', $this->formData(new User, $request));
    }

    public function store(Request $request)
    {
        HcisAccess::ensurePermissions();

        $data = $this->validated($request);
        $this->guardAssignableRoles($request, $data['roles']);
        $this->authorizeCompanyIds($request, $data['company_ids'] ?? []);
        $user = User::create([
            'employee_id' => null,
            'name' => $data['name'],
            'email' => $data['email'],
            'nrp' => $data['nrp'],
            'password' => Hash::make($data['password']),
            'is_active' => $request->boolean('is_active'),
        ]);
        $user->syncRoles($data['roles']);
        $this->syncCompanyAccess($user, $data);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('users.edit', $user)->with('success', 'User berhasil dibuat.');
    }

    public function edit(Request $request, User $user)
    {
        HcisAccess::ensurePermissions();
        $this->guardManagedUser($request, $user);
        $user->load(['companies', 'employee', 'roles']);

        return view('users.form', $this->formData($user, $request));
    }

    public function update(Request $request, User $user)
    {
        HcisAccess::ensurePermissions();

        $data = $this->validated($request, $user);
        $this->guardManagedUser($request, $user);
        $this->guardAssignableRoles($request, $data['roles']);
        $this->authorizeCompanyIds($request, $data['company_ids'] ?? []);
        $payload = [
            'employee_id' => null,
            'name' => $data['name'],
            'email' => $data['email'],
            'nrp' => $data['nrp'],
            'is_active' => $request->boolean('is_active'),
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $this->guardLastSuperAdmin($user, $data['roles']);
        $user->update($payload);
        $user->syncRoles($data['roles']);
        $this->syncCompanyAccess($user, $data);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('users.edit', $user)->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'User yang sedang login tidak boleh dihapus.');
        $this->guardManagedUser($request, $user);
        $this->guardLastSuperAdmin($user, []);

        $user->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('users.index')->with('success', 'User berhasil dihapus.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'nrp' => ['required', 'string', 'max:20', Rule::unique('users', 'nrp')->ignore($user?->id)],
            'password' => [$user?->exists ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer', Rule::exists('companies', 'id')],
        ]);
    }

    private function formData(User $user, Request $request): array
    {
        $companyIds = CompanyAccess::ids($request->user());

        return [
            'account' => $user,
            'companies' => Company::whereIn('id', $companyIds)->orderBy('name')->get(),
            'roles' => Role::orderByRaw("case when name = 'super_admin' then 0 when name like 'module_%' then 2 else 1 end")->orderBy('name')->get(),
            'selectedRoles' => $user->exists ? $user->roles->pluck('name')->all() : [],
            'selectedCompanies' => $user->exists ? $user->companies->pluck('id')->all() : [],
        ];
    }

    private function guardLastSuperAdmin(User $user, array $nextRoles): void
    {
        if (! $user->exists || ! $user->hasRole('super_admin') || in_array('super_admin', $nextRoles, true)) {
            return;
        }

        $otherSuperAdmins = User::role('super_admin')->whereKeyNot($user->id)->count();
        abort_if($otherSuperAdmins === 0, 422, 'Minimal harus ada satu user super_admin aktif.');
    }

    private function guardAssignableRoles(Request $request, array $nextRoles): void
    {
        abort_if(! $request->user()->hasRole('super_admin') && in_array('super_admin', $nextRoles, true), 403, 'Hanya super admin yang boleh memberi role super_admin.');
    }

    private function authorizeCompanyIds(Request $request, array $companyIds): void
    {
        foreach ($companyIds as $companyId) {
            CompanyAccess::authorize($request->user(), $companyId);
        }
    }

    private function syncCompanyAccess(User $user, array $data): void
    {
        $companyIds = collect($data['company_ids'] ?? [])->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $user->companies()->sync($companyIds->mapWithKeys(fn ($id) => [$id => ['access_level' => 'company']])->all());
    }

    private function guardManagedUser(Request $request, User $user): void
    {
        if ($request->user()->hasRole('super_admin')) {
            return;
        }

        abort_if($user->hasRole('super_admin'), 403, 'Akun super admin hanya bisa dikelola oleh super admin.');

        $companyIds = CompanyAccess::ids($request->user());
        $manageable = $user->companies()->whereIn('companies.id', $companyIds)->exists();

        abort_unless($manageable, 403, 'User ini berada di luar akses perusahaan Anda.');
    }
}
