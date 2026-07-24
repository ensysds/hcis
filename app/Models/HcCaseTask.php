<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HcCaseTask extends Model
{
    protected $guarded = [];
    protected $casts = ['due_date' => 'date', 'completed_at' => 'datetime'];
    public function hcCase() { return $this->belongsTo(HcCase::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
}
