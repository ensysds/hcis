<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('core_role', 30)->default('employee')->after('status');
            $table->string('core_password')->nullable()->after('core_role');
            $table->boolean('core_is_active')->default(true)->after('core_password');
            $table->boolean('core_must_change_password')->default(true)->after('core_is_active');
            $table->timestamp('core_password_reset_requested_at')->nullable()->after('core_must_change_password');
            $table->timestamp('core_last_login_at')->nullable()->after('core_password_reset_requested_at');
        });

        DB::table('employees')->orderBy('id')->each(function ($employee) {
            if (! $employee->join_date || ! $employee->birth_date) {
                return;
            }

            DB::table('employees')->where('id', $employee->id)->update([
                'core_password' => Hash::make(self::defaultCorePassword($employee->nrp, $employee->join_date, $employee->birth_date)),
                'core_is_active' => $employee->status === 'active',
                'core_must_change_password' => true,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'core_role',
                'core_password',
                'core_is_active',
                'core_must_change_password',
                'core_password_reset_requested_at',
                'core_last_login_at',
            ]);
        });
    }

    private static function defaultCorePassword(string $nrp, string $joinDate, string $birthDate): string
    {
        return substr($nrp, -4).date('dmy', strtotime($joinDate)).date('dmy', strtotime($birthDate));
    }
};
