<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_company_access')
            ->withPivot(['department_id', 'access_level'])
            ->withTimestamps();
    }

    public function solutionModules()
    {
        return $this->belongsToMany(SolutionModule::class, 'company_solution_modules')
            ->withPivot(['is_active', 'settings'])
            ->withTimestamps();
    }

    public function coreAccessRoles()
    {
        return $this->hasMany(CoreAccessRole::class);
    }

    public function workCalendars() { return $this->hasMany(WorkCalendar::class); }
    public function contracts() { return $this->hasMany(EmploymentContract::class); }
    public function cases() { return $this->hasMany(HcCase::class); }
}
