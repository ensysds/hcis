<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('registration_number')->nullable()->after('tax_number');
            $table->string('tax_office')->nullable()->after('registration_number');
            $table->string('phone', 50)->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
            $table->string('website')->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('website');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->string('type', 30)->default('department')->after('name');
            $table->text('description')->nullable()->after('type');
            $table->boolean('is_active')->default(true)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('departments', fn (Blueprint $table) => $table->dropColumn(['type', 'description', 'is_active']));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn(['legal_name', 'registration_number', 'tax_office', 'phone', 'email', 'website', 'is_active']));
    }
};
