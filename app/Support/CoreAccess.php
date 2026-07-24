<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Collection;

class CoreAccess
{
    public static function modulesFor(Employee $employee): Collection
    {
        $employee->loadMissing('coreAccessRoles.modules');

        $activeForCompany = $employee->company
            ? $employee->company->solutionModules()
                ->where('solution_modules.is_active', true)
                ->wherePivot('is_active', true)
                ->pluck('solution_modules.id')
            : collect();

        return $employee->coreAccessRoles
            ->where('is_active', true)
            ->flatMap(fn ($role) => $role->modules)
            ->filter(fn ($module) => $activeForCompany->contains($module->id))
            ->groupBy('id')
            ->map(function (Collection $assignments) {
                $module = $assignments->first();
                $abilities = $assignments
                    ->flatMap(fn ($assignment) => self::decodeAbilities($assignment->pivot->abilities))
                    ->unique()
                    ->values();

                return [
                    'key' => $module->key,
                    'solution' => $module->solution,
                    'name' => $module->name,
                    'label' => $module->core_label,
                    'description' => $module->description,
                    'icon' => $module->icon,
                    'sort_order' => $module->sort_order,
                    'abilities' => $abilities->all(),
                ];
            })
            ->sortBy('sort_order')
            ->values();
    }

    public static function allows(Employee $employee, string $module, string $ability = 'view'): bool
    {
        $access = self::modulesFor($employee)->firstWhere('key', $module);

        return $access && in_array($ability, $access['abilities'], true);
    }

    private static function decodeAbilities(mixed $abilities): array
    {
        if (is_array($abilities)) {
            return $abilities;
        }

        $decoded = json_decode((string) $abilities, true);

        return is_array($decoded) ? $decoded : ['view'];
    }
}
