<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolutionModule extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_solution_modules')
            ->withPivot(['is_active', 'settings'])
            ->withTimestamps();
    }

    public function coreRoles()
    {
        return $this->belongsToMany(CoreAccessRole::class, 'core_access_role_module')
            ->withPivot('abilities')
            ->withTimestamps();
    }
}
