<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkCalendar extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = ['working_days' => 'array', 'is_default' => 'boolean', 'is_active' => 'boolean'];
    public function company() { return $this->belongsTo(Company::class); }
    public function holidays() { return $this->hasMany(CalendarHoliday::class); }
}
