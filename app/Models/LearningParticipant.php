<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningParticipant extends Model
{
    protected $guarded = [];
    protected $casts = ['passed' => 'boolean', 'certificate_expiry_date' => 'date', 'score' => 'decimal:2', 'actual_cost' => 'decimal:2'];
    public function program() { return $this->belongsTo(LearningProgram::class, 'learning_program_id'); }
    public function employee() { return $this->belongsTo(Employee::class); }
}
