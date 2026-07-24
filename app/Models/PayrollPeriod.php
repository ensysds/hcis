<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollPeriod extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'pay_date' => 'date', 'finalized_at' => 'datetime', 'locked_at' => 'datetime'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }
}
