<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceReview extends Model
{
    protected $guarded = [];
    protected $casts = ['goals' => 'array', 'goal_score' => 'decimal:2', 'competency_score' => 'decimal:2', 'final_score' => 'decimal:2'];
    public function cycle() { return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id'); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function reviewer() { return $this->belongsTo(Employee::class, 'reviewer_id'); }
}
