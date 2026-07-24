<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $guarded = [];

    protected $casts = ['requires_attachment' => 'boolean', 'deduct_balance' => 'boolean'];
}
