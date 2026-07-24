<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class PayrollAdjustment extends Model { use SoftDeletes; protected $guarded=[]; protected $casts=['amount'=>'decimal:2']; public function period(){return $this->belongsTo(PayrollPeriod::class,'payroll_period_id');} public function employee(){return $this->belongsTo(Employee::class);} }
