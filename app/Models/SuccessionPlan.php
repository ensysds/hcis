<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuccessionPlan extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    public function company() { return $this->belongsTo(Company::class); }
    public function position() { return $this->belongsTo(Position::class); }
    public function candidate() { return $this->belongsTo(Employee::class, 'candidate_employee_id'); }
}
