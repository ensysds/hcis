<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OvertimeRequest extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['date' => 'date', 'calculated_amount' => 'decimal:2'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
