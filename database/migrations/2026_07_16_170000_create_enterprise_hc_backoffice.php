<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_company_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('access_level', 20)->default('company'); // company/department/read_only
            $table->timestamps();
            $table->unique(['user_id', 'company_id', 'department_id'], 'user_company_scope_unique');
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->text('notes')->nullable();
        });

        Schema::table('approval_flows', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('approver_type', 30)->default('role');
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('minimum_amount', 15, 2)->nullable();
            $table->decimal('maximum_amount', 15, 2)->nullable();
            $table->unsignedInteger('sla_hours')->nullable();
        });

        Schema::table('approval_requests', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2)->nullable();
            $table->json('context')->nullable();
        });

        Schema::create('work_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('timezone')->default('Asia/Jakarta');
            $table->json('working_days')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('calendar_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_calendar_id')->constrained()->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name');
            $table->string('type', 30)->default('public');
            $table->boolean('is_paid')->default(true);
            $table->timestamps();
            $table->unique(['work_calendar_id', 'holiday_date']);
        });

        Schema::create('employment_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('contract_number')->unique();
            $table->string('contract_type', 30);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status', 'end_date']);
        });

        Schema::create('employee_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no')->unique();
            $table->string('action_type', 30);
            $table->date('effective_date');
            $table->foreignId('from_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('to_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('from_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('to_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('from_job_grade_id')->nullable()->constrained('job_grades')->nullOnDelete();
            $table->foreignId('to_job_grade_id')->nullable()->constrained('job_grades')->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->text('reason')->nullable();
            $table->json('changes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'action_type', 'effective_date']);
        });

        Schema::create('hc_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_no')->unique();
            $table->string('case_type', 40);
            $table->string('subject');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('open');
            $table->date('opened_date');
            $table->date('target_date')->nullable();
            $table->date('closed_date')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 2)->nullable();
            $table->text('description')->nullable();
            $table->json('details')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'case_type', 'status']);
        });

        Schema::create('hc_case_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hc_case_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->string('category', 50);
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_value', 15, 2)->nullable();
            $table->string('condition', 30)->default('good');
            $table->string('status', 30)->default('available');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('assigned_date');
            $table->date('expected_return_date')->nullable();
            $table->date('returned_date')->nullable();
            $table->string('condition_out', 30)->nullable();
            $table->string('condition_in', 30)->nullable();
            $table->string('status', 30)->default('assigned');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('performance_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 30)->default('draft');
            $table->unsignedTinyInteger('goal_weight')->default(70);
            $table->unsignedTinyInteger('competency_weight')->default(30);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->decimal('goal_score', 5, 2)->nullable();
            $table->decimal('competency_score', 5, 2)->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('rating', 20)->nullable();
            $table->string('status', 30)->default('not_started');
            $table->text('strengths')->nullable();
            $table->text('development_areas')->nullable();
            $table->json('goals')->nullable();
            $table->timestamps();
            $table->unique(['performance_cycle_id', 'employee_id']);
        });

        Schema::create('learning_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('type', 30)->default('training');
            $table->string('provider')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget', 15, 2)->default(0);
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status', 30)->default('planned');
            $table->text('objectives')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('learning_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('registered');
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->string('certificate_number')->nullable();
            $table->date('certificate_expiry_date')->nullable();
            $table->decimal('actual_cost', 15, 2)->default(0);
            $table->text('evaluation')->nullable();
            $table->timestamps();
            $table->unique(['learning_program_id', 'employee_id']);
        });

        Schema::create('succession_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('readiness', 30)->default('long_term');
            $table->string('risk_of_loss', 20)->nullable();
            $table->string('performance_box', 20)->nullable();
            $table->unsignedTinyInteger('priority')->default(1);
            $table->text('development_plan')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['position_id', 'candidate_employee_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('succession_plans');
        Schema::dropIfExists('learning_participants');
        Schema::dropIfExists('learning_programs');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('performance_cycles');
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('hc_case_tasks');
        Schema::dropIfExists('hc_cases');
        Schema::dropIfExists('employee_actions');
        Schema::dropIfExists('employment_contracts');
        Schema::dropIfExists('calendar_holidays');
        Schema::dropIfExists('work_calendars');
        Schema::table('approval_requests', fn (Blueprint $table) => $table->dropColumn(['company_id', 'amount', 'context']));
        Schema::table('approval_flows', fn (Blueprint $table) => $table->dropColumn(['company_id', 'approver_type', 'approver_user_id', 'minimum_amount', 'maximum_amount', 'sla_hours']));
        Schema::table('payroll_periods', fn (Blueprint $table) => $table->dropColumn(['company_id', 'locked_by', 'locked_at', 'notes']));
        Schema::dropIfExists('user_company_access');
    }
};
