<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeChild extends Model
{
    protected $guarded = [];

    protected $casts = ['birth_date' => 'date'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
