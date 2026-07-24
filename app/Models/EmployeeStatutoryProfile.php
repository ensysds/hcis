<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EmployeeStatutoryProfile extends Model { protected $guarded=[]; protected $casts=['effective_date'=>'date','end_date'=>'date','bpjs_health_active'=>'boolean','bpjs_employment_active'=>'boolean']; public function employee(){return $this->belongsTo(Employee::class);} }
