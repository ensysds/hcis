<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Shift extends Model { use SoftDeletes; protected $guarded=[]; protected $casts=['crosses_midnight'=>'boolean','is_active'=>'boolean']; public function company(){return $this->belongsTo(Company::class);} public function schedules(){return $this->hasMany(ShiftSchedule::class);} }
