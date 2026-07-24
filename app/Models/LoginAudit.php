<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAudit extends Model
{
    protected $guarded = [];

    protected $casts = ['successful' => 'boolean'];
}
