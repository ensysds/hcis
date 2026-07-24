<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreApiToken extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
