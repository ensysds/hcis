<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetAssignment extends Model
{
    protected $guarded = [];
    protected $casts = ['assigned_date' => 'date', 'expected_return_date' => 'date', 'returned_date' => 'date'];
    public function asset() { return $this->belongsTo(Asset::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
}
