<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = ['purchase_date' => 'date', 'purchase_value' => 'decimal:2'];
    public function company() { return $this->belongsTo(Company::class); }
    public function assignments() { return $this->hasMany(AssetAssignment::class); }
    public function activeAssignment() { return $this->hasOne(AssetAssignment::class)->where('status', 'assigned')->latestOfMany(); }
}
