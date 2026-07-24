<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningProgram extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'budget' => 'decimal:2'];
    public function company() { return $this->belongsTo(Company::class); }
    public function participants() { return $this->hasMany(LearningParticipant::class); }
}
