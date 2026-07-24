<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeAction extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = ['effective_date' => 'date', 'approved_at' => 'datetime', 'changes' => 'array'];

    public function company() { return $this->belongsTo(Company::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function toCompany() { return $this->belongsTo(Company::class, 'to_company_id'); }
    public function toDepartment() { return $this->belongsTo(Department::class, 'to_department_id'); }
    public function toPosition() { return $this->belongsTo(Position::class, 'to_position_id'); }
}
