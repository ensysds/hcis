<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreAccessRole extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function modules()
    {
        return $this->belongsToMany(SolutionModule::class, 'core_access_role_module')
            ->withPivot('abilities')
            ->withTimestamps();
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_core_access_role')
            ->withPivot(['scope_type', 'scope_id'])
            ->withTimestamps();
    }
}
