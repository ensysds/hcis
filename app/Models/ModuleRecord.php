<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModuleRecord extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['record_date' => 'date', 'details' => 'array'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
