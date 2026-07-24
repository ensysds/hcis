<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class HcisAccess
{
    public static function actions(): array
    {
        return [
            'view' => 'View',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'approve' => 'Approve',
            'export' => 'Export',
        ];
    }

    public static function modules(): array
    {
        return [
            'company' => ['label' => 'Company Management', 'group' => 'Organization Development', 'icon' => 'buildings'],
            'employee' => ['label' => 'Employee Database', 'group' => 'Organization Development', 'icon' => 'people'],
            'organization' => ['label' => 'Organization Management', 'group' => 'Organization Development', 'icon' => 'diagram-3'],
            'lifecycle' => ['label' => 'Employee Lifecycle', 'group' => 'Organization Development', 'icon' => 'person-lines-fill'],
            'attendance' => ['label' => 'Administrasi Kehadiran', 'group' => 'Time Management', 'icon' => 'clock'],
            'shift' => ['label' => 'Shift & Schedule', 'group' => 'Time Management', 'icon' => 'calendar3', 'route' => 'shifts'],
            'leave' => ['label' => 'Administrasi Cuti', 'group' => 'Time Management', 'icon' => 'calendar-check'],
            'overtime' => ['label' => 'Administrasi Lembur', 'group' => 'Time Management', 'icon' => 'stopwatch'],
            'payroll' => ['label' => 'Payroll', 'group' => 'Payroll', 'icon' => 'wallet2'],
            'tax' => ['label' => 'PPh 21', 'group' => 'Payroll', 'icon' => 'calculator', 'route' => 'tax'],
            'bpjs' => ['label' => 'BPJS', 'group' => 'Payroll', 'icon' => 'shield-check', 'route' => 'bpjs'],
            'recruitment' => ['label' => 'Recruitment & ATS', 'group' => 'Talent Management', 'icon' => 'briefcase', 'route' => 'recruitment'],
            'onboarding' => ['label' => 'Onboarding', 'group' => 'Talent Management', 'icon' => 'person-check', 'route' => 'onboarding'],
            'offboarding' => ['label' => 'Offboarding', 'group' => 'Talent Management', 'icon' => 'box-arrow-right', 'route' => 'offboarding'],
            'performance' => ['label' => 'Performance & KPI', 'group' => 'People Development', 'icon' => 'graph-up-arrow', 'route' => 'performance'],
            'competency' => ['label' => 'Competency', 'group' => 'People Development', 'icon' => 'award', 'route' => 'competency'],
            'career' => ['label' => 'Career & Succession', 'group' => 'People Development', 'icon' => 'signpost-split', 'route' => 'career'],
            'learning' => ['label' => 'Learning & Development', 'group' => 'People Development', 'icon' => 'mortarboard', 'route' => 'learning'],
            'knowledge' => ['label' => 'Knowledge Management', 'group' => 'People Development', 'icon' => 'journal-text', 'route' => 'knowledge'],
            'innovation' => ['label' => 'Innovation Management', 'group' => 'People Development', 'icon' => 'lightbulb', 'route' => 'innovation'],
            'loan' => ['label' => 'Loan / Kasbon', 'group' => 'HC Operations', 'icon' => 'cash-coin', 'route' => 'loans'],
            'claim' => ['label' => 'Reimbursement', 'group' => 'HC Operations', 'icon' => 'receipt', 'route' => 'claims'],
            'disciplinary' => ['label' => 'Disciplinary / SP', 'group' => 'HC Operations', 'icon' => 'exclamation-triangle', 'route' => 'disciplinary'],
            'document' => ['label' => 'Document & Letter', 'group' => 'HC Operations', 'icon' => 'file-earmark-text', 'route' => 'documents'],
            'hc_case' => ['label' => 'HC Process & Case Center', 'group' => 'HC Operations', 'icon' => 'folder2-open'],
            'asset' => ['label' => 'Asset Management', 'group' => 'HC Operations', 'icon' => 'laptop', 'route' => 'assets'],
            'approval' => ['label' => 'Approval Inbox', 'group' => 'HC Operations', 'icon' => 'inbox'],
            'report' => ['label' => 'Reports & Analytics', 'group' => 'Reporting', 'icon' => 'bar-chart', 'route' => 'reports'],
            'settings' => ['label' => 'System Settings', 'group' => 'Administrator', 'icon' => 'gear', 'route' => 'settings'],
            'policy' => ['label' => 'HC Policy & Workflow', 'group' => 'Administrator', 'icon' => 'sliders'],
            'user' => ['label' => 'User Management', 'group' => 'Administrator', 'icon' => 'person-gear'],
            'role' => ['label' => 'Role Management', 'group' => 'Administrator', 'icon' => 'shield-lock'],
            'core_access' => ['label' => 'Core Access', 'group' => 'Administrator', 'icon' => 'grid-3x3-gap'],
        ];
    }

    public static function sidebarGroups(): array
    {
        return [
            'ORGANIZATION DEVELOPMENT' => ['company', 'organization', 'employee', 'lifecycle'],
            'TIME MANAGEMENT' => ['attendance', 'shift', 'leave', 'overtime'],
            'PAYROLL' => ['payroll', 'tax', 'bpjs'],
            'TALENT MANAGEMENT' => ['recruitment', 'onboarding', 'offboarding'],
            'PEOPLE DEVELOPMENT' => ['performance', 'competency', 'career', 'learning', 'knowledge', 'innovation'],
            'HC OPERATIONS' => ['hc_case', 'loan', 'claim', 'disciplinary', 'document', 'asset', 'approval'],
            'REPORTING' => ['report'],
            'ADMINISTRATOR' => ['policy', 'settings', 'user', 'role', 'core_access'],
        ];
    }

    public static function sidebarItem(string $module): array
    {
        $config = self::modules()[$module];
        $directRoutes = self::directSidebarRoutes();

        if (isset($directRoutes[$module])) {
            $definition = is_array($directRoutes[$module])
                ? $directRoutes[$module]
                : ['route_name' => $directRoutes[$module]];

            return $config + [
                'route_name' => $definition['route_name'],
                'route_parameters' => $definition['route_parameters'] ?? [],
                'active_routes' => $definition['active_routes'] ?? [$definition['route_name']],
            ];
        }

        return $config + [
            'route_name' => 'modules.index',
            'route_parameters' => [$config['route']],
            'active_routes' => ['modules.*'],
        ];
    }

    public static function isSidebarItemActive(string $module): bool
    {
        $item = self::sidebarItem($module);

        if (request()->routeIs('modules.*')) {
            return in_array((string) request()->route('module'), self::moduleRecordAliases($module), true);
        }

        $menuKey = $item['route_parameters']['menu'] ?? null;
        if ($menuKey !== null) {
            return request()->routeIs($item['route_name']) && request()->query('menu') === $menuKey;
        }

        return request()->routeIs(...$item['active_routes']);
    }

    public static function moduleRecordKey(string $module): string
    {
        foreach (self::routeModules() as $routeKey => $config) {
            if ($routeKey === $module || ($config['permission'] ?? null) === $module) {
                return $config['permission'];
            }
        }

        return $module;
    }

    public static function moduleRecordAliases(string $module): array
    {
        $aliases = [$module, self::moduleRecordKey($module)];

        foreach (self::routeModules() as $routeKey => $config) {
            if ($routeKey === $module || ($config['permission'] ?? null) === $module) {
                $aliases[] = $routeKey;
                $aliases[] = $config['permission'];
            }
        }

        return collect($aliases)->filter()->unique()->values()->all();
    }

    public static function routeModules(): array
    {
        return collect(self::modules())
            ->filter(fn (array $module) => isset($module['route']))
            ->mapWithKeys(fn (array $module, string $permissionKey) => [
                $module['route'] => $module + ['permission' => $permissionKey],
            ])
            ->all();
    }

    public static function routeModule(string $routeKey): ?array
    {
        return self::routeModules()[$routeKey] ?? null;
    }

    public static function permissionNames(): array
    {
        $names = [];
        foreach (array_keys(self::modules()) as $module) {
            foreach (array_keys(self::actions()) as $action) {
                $names[] = self::permissionName($module, $action);
            }
        }

        return $names;
    }

    public static function permissionName(string $module, string $action): string
    {
        return "{$module}.{$action}";
    }

    public static function ensurePermissions(): void
    {
        foreach (self::permissionNames() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function ensureDefaultModuleRoles(): void
    {
        self::ensurePermissions();

        foreach (array_keys(self::modules()) as $module) {
            $role = Role::firstOrCreate(['name' => "module_{$module}", 'guard_name' => 'web']);

            if ($role->wasRecentlyCreated || $role->permissions()->count() === 0) {
                $role->syncPermissions(self::defaultPermissionNamesFor($module));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function defaultPermissionNamesFor(string $module): array
    {
        return collect(['view', 'create', 'update', 'delete'])
            ->map(fn (string $action) => self::permissionName($module, $action))
            ->all();
    }

    public static function permissionOptions(): Collection
    {
        self::ensurePermissions();

        return Permission::whereIn('name', self::permissionNames())->orderBy('name')->get();
    }

    private static function directSidebarRoutes(): array
    {
        return [
            'company' => ['route_name' => 'companies.index', 'active_routes' => ['companies.*']],
            'employee' => ['route_name' => 'employees.index', 'active_routes' => ['employees.*']],
            'organization' => ['route_name' => 'organization.index', 'active_routes' => ['organization.*', 'organization.positions.*']],
            'lifecycle' => ['route_name' => 'enterprise.lifecycle', 'active_routes' => ['enterprise.lifecycle', 'enterprise.contracts.*', 'enterprise.actions.*']],
            'attendance' => ['route_name' => 'attendance.index', 'active_routes' => ['attendance.*']],
            'shift' => ['route_name' => 'enterprise.time', 'route_parameters' => ['menu' => 'shift'], 'active_routes' => ['enterprise.time', 'enterprise.shifts.*', 'enterprise.schedules.*']],
            'leave' => ['route_name' => 'leave.index', 'active_routes' => ['leave.*']],
            'overtime' => ['route_name' => 'overtime.index', 'active_routes' => ['overtime.*']],
            'payroll' => ['route_name' => 'payroll.index', 'active_routes' => ['payroll.*', 'payslips.*']],
            'tax' => ['route_name' => 'enterprise.payroll-setup', 'route_parameters' => ['menu' => 'tax']],
            'bpjs' => ['route_name' => 'enterprise.payroll-setup', 'route_parameters' => ['menu' => 'bpjs']],
            'hc_case' => ['route_name' => 'enterprise.cases', 'active_routes' => ['enterprise.cases', 'enterprise.cases.*', 'enterprise.case-tasks.*']],
            'asset' => ['route_name' => 'enterprise.assets', 'active_routes' => ['enterprise.assets', 'enterprise.assets.*']],
            'performance' => ['route_name' => 'enterprise.talent', 'route_parameters' => ['menu' => 'performance']],
            'learning' => ['route_name' => 'enterprise.talent', 'route_parameters' => ['menu' => 'learning']],
            'career' => ['route_name' => 'enterprise.talent', 'route_parameters' => ['menu' => 'career']],
            'report' => ['route_name' => 'enterprise.reports', 'active_routes' => ['enterprise.reports']],
            'policy' => ['route_name' => 'enterprise.policies', 'active_routes' => ['enterprise.policies', 'enterprise.calendars.*', 'enterprise.holidays.*', 'enterprise.flows.*']],
            'approval' => ['route_name' => 'approvals.index', 'active_routes' => ['approvals.*']],
            'user' => ['route_name' => 'users.index', 'active_routes' => ['users.*']],
            'role' => ['route_name' => 'roles.index', 'active_routes' => ['roles.*']],
            'core_access' => ['route_name' => 'core-access.index', 'active_routes' => ['core-access.*']],
        ];
    }
}
