<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmploymentContract extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'probation_end_date' => 'date', 'approved_at' => 'datetime'];

    public function company() { return $this->belongsTo(Company::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
}
