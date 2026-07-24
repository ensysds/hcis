<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function positions()
    {
        return $this->hasMany(Position::class)->orderBy('name');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
