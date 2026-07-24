<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$keepEmail = 'superadmin.hcis@ensys.id';
$user = User::where('email', $keepEmail)->firstOrFail();

$dbPath = database_path('database.sqlite');
$backupDir = storage_path('app/backups');
if (! is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}
$backupPath = $backupDir.'/database-before-purge-'.date('Ymd-His').'.sqlite';
copy($dbPath, $backupPath);

$driver = DB::getDriverName();
if ($driver === 'sqlite') {
    DB::statement('PRAGMA foreign_keys = OFF');
} elseif ($driver === 'mysql') {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
}

DB::beginTransaction();
try {
    $tables = collect(Schema::getTables())->pluck('name')->all();
    $preserve = [
        'migrations',
        'permissions',
        'roles',
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',
        'users',
        'sqlite_sequence',
    ];

    foreach ($tables as $table) {
        if (in_array($table, $preserve, true)) {
            continue;
        }

        DB::table($table)->delete();
    }

    DB::table('model_has_permissions')->where('model_id', '!=', $user->id)->delete();
    DB::table('model_has_roles')->where('model_id', '!=', $user->id)->delete();
    DB::table('users')->where('id', '!=', $user->id)->delete();

    DB::table('role_has_permissions')->delete();
    DB::table('model_has_permissions')->delete();
    DB::table('model_has_roles')->delete();
    DB::table('roles')->where('name', '!=', 'super_admin')->delete();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $role->syncPermissions(Permission::all());

    $user->forceFill([
        'employee_id' => null,
        'nrp' => 'SYSADMIN',
        'is_active' => true,
    ])->save();
    $user->syncRoles([$role]);

    if ($driver === 'sqlite') {
        foreach ($tables as $table) {
            if ($table === 'sqlite_sequence') {
                continue;
            }
            DB::table('sqlite_sequence')->where('name', $table)->delete();
        }
    }

    DB::commit();
} catch (Throwable $e) {
    DB::rollBack();
    throw $e;
} finally {
    if ($driver === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = ON');
    } elseif ($driver === 'mysql') {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}

app(PermissionRegistrar::class)->forgetCachedPermissions();

echo "Backup: {$backupPath}".PHP_EOL;
echo "Kept user: {$keepEmail}".PHP_EOL;
