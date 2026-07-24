<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$backupPath = __DIR__.'/../storage/app/backups/database-before-employee-profile-20260716-143943.sqlite';
$backup = new SQLite3($backupPath);
$row = $backup->querySingle(
    "select email, name, password, is_active, email_verified_at from users where email = 'superadmin.hcis@ensys.id'",
    true
);

if (! $row) {
    fwrite(STDERR, "Backup user not found.\n");
    exit(1);
}

$user = User::updateOrCreate(
    ['email' => $row['email']],
    [
        'name' => $row['name'] ?: 'Super Administrator',
        'password' => $row['password'],
        'is_active' => true,
        'email_verified_at' => $row['email_verified_at'] ?: now(),
        'employee_id' => null,
        'nrp' => 'SYSADMIN',
    ]
);

Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
$user->syncRoles(['super_admin']);

echo $user->email.' restored with super_admin role'.PHP_EOL;
