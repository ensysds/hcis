<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class AttendanceCorrection extends Model { use SoftDeletes; protected $guarded=[]; protected $casts=['requested_in'=>'datetime','requested_out'=>'datetime','approved_at'=>'datetime']; public function attendance(){return $this->belongsTo(Attendance::class);} public function employee(){return $this->belongsTo(Employee::class);} }
