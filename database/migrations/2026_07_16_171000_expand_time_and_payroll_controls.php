<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('break_minutes')->default(60);
            $table->boolean('crosses_midnight')->default(false);
            $table->boolean('is_active')->default(true);
        });
        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
        });
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->foreignId('overtime_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('calculated_amount', 15, 2)->default(0);
        });
        Schema::table('leave_types', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('minimum_notice_days')->default(0);
            $table->unsignedInteger('maximum_consecutive_days')->nullable();
            $table->decimal('carry_forward_limit', 5, 1)->default(0);
            $table->boolean('allow_half_day')->default(false);
            $table->boolean('is_active')->default(true);
        });
        Schema::table('salary_components', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('calculation_method', 30)->default('fixed');
            $table->boolean('include_in_bpjs_basis')->default(false);
            $table->unsignedInteger('display_order')->default(100);
        });
        Schema::table('module_records', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['company_id', 'module', 'status']);
        });

        DB::table('module_records')
            ->whereNotNull('employee_id')
            ->update(['company_id' => DB::raw('(select company_id from employees where employees.id = module_records.employee_id)')]);

        $fallbackCompanyId = DB::table('companies')->orderBy('id')->value('id');
        if ($fallbackCompanyId) {
            DB::table('module_records')->whereNull('company_id')->update(['company_id' => $fallbackCompanyId]);
        }

        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_code')->nullable();
            $table->string('employee_reference');
            $table->dateTime('recorded_at');
            $table->string('event_type', 20)->default('unknown');
            $table->json('raw_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'recorded_at']);
        });

        Schema::create('overtime_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('weekday_multiplier', 6, 3)->default(1.5);
            $table->decimal('weekend_multiplier', 6, 3)->default(2);
            $table->decimal('holiday_multiplier', 6, 3)->default(2);
            $table->decimal('minimum_hours', 5, 2)->default(0);
            $table->decimal('maximum_hours_per_day', 5, 2)->nullable();
            $table->unsignedInteger('monthly_divisor')->default(173);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('statutory_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('config_type', 30);
            $table->string('code', 50);
            $table->string('name');
            $table->decimal('employee_rate', 8, 4)->default(0);
            $table->decimal('employer_rate', 8, 4)->default(0);
            $table->decimal('minimum_basis', 15, 2)->nullable();
            $table->decimal('maximum_basis', 15, 2)->nullable();
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->json('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'config_type', 'code', 'effective_date'], 'statutory_config_unique');
        });

        Schema::create('employee_statutory_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('tax_status', 20)->nullable();
            $table->string('tax_method', 20)->default('gross');
            $table->string('npwp')->nullable();
            $table->string('bpjs_health_number')->nullable();
            $table->string('bpjs_employment_number')->nullable();
            $table->boolean('bpjs_health_active')->default(false);
            $table->boolean('bpjs_employment_active')->default(false);
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'effective_date']);
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_no')->unique();
            $table->string('name');
            $table->string('type', 20);
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->string('status', 20)->default('approved');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('employee_statutory_profiles');
        Schema::dropIfExists('statutory_configs');
        Schema::dropIfExists('overtime_policies');
        Schema::dropIfExists('attendance_logs');
        Schema::table('module_records', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'module', 'status']);
            $table->dropConstrainedForeignId('company_id');
        });
        Schema::table('overtime_requests', fn (Blueprint $table) => $table->dropColumn(['overtime_policy_id', 'calculated_amount']));
        Schema::table('salary_components', fn (Blueprint $table) => $table->dropColumn(['company_id', 'calculation_method', 'include_in_bpjs_basis', 'display_order']));
        Schema::table('leave_types', fn (Blueprint $table) => $table->dropColumn(['company_id', 'minimum_notice_days', 'maximum_consecutive_days', 'carry_forward_limit', 'allow_half_day', 'is_active']));
        Schema::table('attendance_corrections', fn (Blueprint $table) => $table->dropColumn(['approved_by', 'approved_at', 'approval_notes']));
        Schema::table('shifts', fn (Blueprint $table) => $table->dropColumn(['company_id', 'break_minutes', 'crosses_midnight', 'is_active']));
    }
};
