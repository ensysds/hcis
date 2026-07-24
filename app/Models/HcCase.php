<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HcCase extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = ['opened_date' => 'date', 'target_date' => 'date', 'closed_date' => 'date', 'details' => 'array', 'amount' => 'decimal:2'];

    public function company() { return $this->belongsTo(Company::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function tasks() { return $this->hasMany(HcCaseTask::class); }
}
