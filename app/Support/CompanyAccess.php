<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CompanyAccess
{
    public static function ids(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        if ($user->hasRole('super_admin')) {
            return \App\Models\Company::query()->pluck('id');
        }

        $assigned = $user->companies()->pluck('companies.id');
        if ($assigned->isNotEmpty()) {
            return $assigned;
        }

        return collect($user->employee?->company_id)->filter();
    }

    public static function scope(Builder $query, ?User $user, string $column = 'company_id'): Builder
    {
        $ids = self::ids($user);

        return $ids->isEmpty() ? $query->whereRaw('1 = 0') : $query->whereIn($column, $ids);
    }

    public static function authorize(?User $user, int|string|null $companyId): void
    {
        abort_unless($companyId && self::ids($user)->contains((int) $companyId), 403, 'Anda tidak memiliki akses ke perusahaan ini.');
    }
}
