<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    protected $guarded = [];

    protected $casts = ['submitted_at' => 'datetime', 'completed_at' => 'datetime', 'amount' => 'decimal:2', 'context' => 'array'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function steps()
    {
        return $this->hasMany(ApprovalStep::class);
    }
}
