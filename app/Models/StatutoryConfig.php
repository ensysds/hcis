<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StatutoryConfig extends Model { protected $guarded=[]; protected $casts=['effective_date'=>'date','end_date'=>'date','rules'=>'array','is_active'=>'boolean']; public function company(){return $this->belongsTo(Company::class);} }
