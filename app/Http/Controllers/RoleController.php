<?php

namespace App\Http\Controllers;

use App\Support\HcisAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        HcisAccess::ensureDefaultModuleRoles();

        $roles = Role::query()
            ->withCount('permissions')
            ->when($request->q, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderByRaw("case when name = 'super_admin' then 0 when name like 'module_%' then 2 else 1 end")
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $userCounts = DB::table('model_has_roles')
            ->select('role_id', DB::raw('count(*) as total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        return view('roles.index', compact('roles', 'userCounts'));
    }

    public function create()
    {
        HcisAccess::ensurePermissions();

        return view('roles.form', $this->formData(new Role));
    }

    public function store(Request $request)
    {
        HcisAccess::ensurePermissions();

        $data = $this->validated($request);
        $role = Role::create([
            'name' => $this->normalizeRoleName($data['name']),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.edit', $role)->with('success', 'Role berhasil dibuat.');
    }

    public function edit(Role $role)
    {
        HcisAccess::ensurePermissions();

        return view('roles.form', $this->formData($role));
    }

    public function update(Request $request, Role $role)
    {
        HcisAccess::ensurePermissions();

        $data = $this->validated($request, $role);

        if ($role->name !== 'super_admin') {
            $role->update(['name' => $this->normalizeRoleName($data['name'])]);
            $role->syncPermissions($data['permissions'] ?? []);
        } else {
            $role->syncPermissions(HcisAccess::permissionNames());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.edit', $role)->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role)
    {
        abort_if($role->name === 'super_admin', 422, 'Role super_admin tidak boleh dihapus.');

        $assignedUsers = DB::table('model_has_roles')->where('role_id', $role->id)->count();
        abort_if($assignedUsers > 0, 422, 'Role masih dipakai user. Lepaskan role dari user terlebih dahulu.');

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', 'Role berhasil dihapus.');
    }

    public function syncDefaults()
    {
        HcisAccess::ensureDefaultModuleRoles();

        return redirect()->route('roles.index')->with('success', 'Role default per modul sudah disinkronkan.');
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        $roleName = $this->normalizeRoleName($request->string('name')->toString());
        $request->merge(['name' => $roleName]);

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:125',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role?->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(HcisAccess::permissionNames())],
        ]);
    }

    private function normalizeRoleName(string $name): string
    {
        return Str::of($name)
            ->lower()
            ->replaceMatches('/[^a-z0-9_\-]+/', '_')
            ->trim('_')
            ->toString();
    }

    private function formData(Role $role): array
    {
        return [
            'role' => $role,
            'modules' => HcisAccess::modules(),
            'actions' => HcisAccess::actions(),
            'selectedPermissions' => $role->exists ? $role->permissions()->pluck('name')->all() : [],
        ];
    }
}
