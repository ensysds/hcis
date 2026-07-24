<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarHoliday extends Model
{
    protected $guarded = [];
    protected $casts = ['holiday_date' => 'date', 'is_paid' => 'boolean'];
    public function calendar() { return $this->belongsTo(WorkCalendar::class, 'work_calendar_id'); }
}
