<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class OvertimePolicy extends Model { use SoftDeletes; protected $guarded=[]; public function company(){return $this->belongsTo(Company::class);} }
