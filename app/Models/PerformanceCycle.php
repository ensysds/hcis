<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceCycle extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];
    public function company() { return $this->belongsTo(Company::class); }
    public function reviews() { return $this->hasMany(PerformanceReview::class); }
}
