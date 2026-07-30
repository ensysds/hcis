<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

class FixedSuperadmin
{
    public const EMAIL = 'superadmin@ensys.id';
    public const PASSWORD = '12345678';
    public const NRP = 'SYSADMIN';

    public static function isEmail(?string $email): bool
    {
        return strcasecmp(trim((string) $email), self::EMAIL) === 0;
    }

    public static function passwordHash(): string
    {
        return Hash::make(self::PASSWORD);
    }
}
