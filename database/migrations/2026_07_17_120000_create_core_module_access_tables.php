<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solution_modules', function (Blueprint $table) {
            $table->id();
            $table->string('solution', 40)->default('hcis');
            $table->string('key', 80);
            $table->string('name', 120);
            $table->string('core_label', 120);
            $table->string('description')->nullable();
            $table->string('icon', 80)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['solution', 'key']);
        });

        Schema::create('company_solution_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('solution_module_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'solution_module_id']);
        });

        Schema::create('core_access_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name', 120);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('core_access_role_module', function (Blueprint $table) {
            $table->id();
            $table->foreignId('core_access_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('solution_module_id')->constrained()->cascadeOnDelete();
            $table->json('abilities')->nullable();
            $table->timestamps();
            $table->unique(['core_access_role_id', 'solution_module_id'], 'core_role_module_unique');
        });

        Schema::create('employee_core_access_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('core_access_role_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type', 40)->default('self');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'core_access_role_id'], 'employee_core_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_core_access_role');
        Schema::dropIfExists('core_access_role_module');
        Schema::dropIfExists('core_access_roles');
        Schema::dropIfExists('company_solution_modules');
        Schema::dropIfExists('solution_modules');
    }
};
