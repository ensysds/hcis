<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ShiftSchedule extends Model { protected $guarded=[]; protected $casts=['date'=>'date']; public function shift(){return $this->belongsTo(Shift::class);} public function employee(){return $this->belongsTo(Employee::class);} }
