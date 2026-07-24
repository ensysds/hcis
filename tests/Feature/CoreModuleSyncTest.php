<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ModuleRecord;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use App\Support\HcisAccess;
use Carbon\Carbon;
use Database\Seeders\CoreAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CoreModuleSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_all_employee_modules_sync_to_hcis_with_dummy_data(): void
    {
        [$company, $employee, $superAdmin, $leaveType] = $this->dummyCoreWorkspace();
        $token = $this->coreTokenFor($employee);

        $bootstrap = $this->withToken($token)->getJson('/api/core/v1/me/bootstrap')
            ->assertOk()
            ->assertJsonPath('user.email', $employee->email);

        $this->assertSame([
            'people',
            'attendance',
            'leave',
            'overtime',
            'payroll',
            'claim',
            'loan',
            'document',
            'bpjs',
            'learning',
            'knowledge',
            'innovation',
            'performance',
            'recruitment',
        ], collect($bootstrap->json('modules'))->pluck('key')->all());

        $this->syncAttendanceFromCore($token, $employee, $superAdmin);
        $this->syncLeaveFromCore($token, $employee, $superAdmin, $leaveType);
        $this->syncOvertimeFromCore($token, $employee, $superAdmin);
        $this->verifyPayrollReadModelInCore($token, $company, $employee);
        $this->syncGenericModuleRecordsFromCore($token, $employee, $superAdmin);
        $this->verifyReadOnlyCoreModulesRejectRecordWrites($token);
    }

    private function dummyCoreWorkspace(): array
    {
        HcisAccess::ensurePermissions();

        $company = Company::create([
            'code' => 'FULL',
            'name' => 'PT Full Core Sync',
            'is_active' => true,
        ]);
        $employee = Employee::create([
            'full_name' => 'Core Full Sync Employee',
            'nrp' => '00123456',
            'email' => 'core.full.sync@test.local',
            'join_date' => '2026-07-17',
            'birth_date' => '1990-01-02',
            'company_id' => $company->id,
            'status' => 'active',
        ]);
        $leaveType = LeaveType::create([
            'company_id' => $company->id,
            'code' => 'ANNUAL-FULL',
            'name' => 'Cuti Tahunan Full Sync',
            'annual_quota' => 12,
            'minimum_notice_days' => 0,
            'carry_forward_limit' => 0,
            'requires_attachment' => false,
            'deduct_balance' => true,
            'allow_half_day' => false,
            'is_active' => true,
        ]);
        LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'entitled' => 12,
            'used' => 0,
        ]);

        OvertimePolicy::create([
            'company_id' => $company->id,
            'name' => 'Core Sync Overtime Policy',
            'weekday_multiplier' => 1.5,
            'weekend_multiplier' => 2,
            'holiday_multiplier' => 2,
            'minimum_hours' => 0,
            'maximum_hours_per_day' => 6,
            'monthly_divisor' => 173,
            'is_active' => true,
        ]);
        $basicComponentId = DB::table('salary_components')->insertGetId([
            'company_id' => $company->id,
            'code' => 'BASIC',
            'name' => 'Gaji Pokok',
            'type' => 'earning',
            'is_taxable' => true,
            'is_active' => true,
            'calculation_method' => 'fixed',
            'include_in_bpjs_basis' => false,
            'display_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_salaries')->insert([
            'employee_id' => $employee->id,
            'salary_component_id' => $basicComponentId,
            'amount' => 10000000,
            'effective_date' => today()->startOfYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $period = PayrollPeriod::create([
            'company_id' => $company->id,
            'name' => 'Juli 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'pay_date' => '2026-07-31',
            'status' => 'finalized',
        ]);
        Payslip::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'gross_amount' => 10000000,
            'deduction_amount' => 500000,
            'net_amount' => 9500000,
            'status' => 'published',
        ]);

        $this->seed(CoreAccessSeeder::class);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions(Permission::all());
        $superAdmin = User::create([
            'name' => 'Full Sync Super Admin',
            'nrp' => 'FULL-SUPER',
            'email' => 'full.sync.super@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $superAdmin->assignRole($superAdminRole);

        return [$company, $employee, $superAdmin, $leaveType];
    }

    private function coreTokenFor(Employee $employee): string
    {
        return $this->postJson('/api/core/v1/auth/login', [
            'login' => $employee->email,
            'password' => $employee->defaultCorePassword(),
            'device_name' => 'Core Full Sync Test',
        ])->assertOk()->json('token');
    }

    private function syncAttendanceFromCore(string $token, Employee $employee, User $superAdmin): void
    {
        $this->withToken($token)->postJson('/api/core/v1/me/attendance/check-in', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])->assertOk()
            ->assertJsonPath('data.status', 'present');
        $this->withToken($token)->postJson('/api/core/v1/me/attendance/check-out')
            ->assertOk();
        $this->withToken($token)->getJson('/api/core/v1/me/attendance')
            ->assertOk()
            ->assertJsonFragment(['status' => 'present']);

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'status' => 'present',
        ]);
        $this->assertNotNull(Attendance::where('employee_id', $employee->id)->first()?->check_out_at);
        $this->actingAs($superAdmin)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee($employee->full_name);
    }

    private function syncLeaveFromCore(string $token, Employee $employee, User $superAdmin, LeaveType $leaveType): void
    {
        $start = today()->next(Carbon::MONDAY);
        $end = $start->copy()->addDay();
        $reason = 'Dummy cuti dari Core full sync';

        $this->withToken($token)->postJson('/api/core/v1/me/leave/requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'reason' => $reason,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.days', 2);
        $this->withToken($token)->getJson('/api/core/v1/me/leave/requests')
            ->assertOk()
            ->assertJsonFragment(['reason' => $reason]);

        $leave = LeaveRequest::where('employee_id', $employee->id)->where('reason', $reason)->firstOrFail();
        $this->assertSame('submitted', $leave->status);
        $this->assertDatabaseHas('approval_requests', [
            'document_type' => 'leave',
            'reference_id' => $leave->id,
            'status' => 'submitted',
        ]);
        $this->actingAs($superAdmin)->get(route('leave.index'))
            ->assertOk()
            ->assertSee($reason);
    }

    private function syncOvertimeFromCore(string $token, Employee $employee, User $superAdmin): void
    {
        $reason = 'Dummy lembur dari Core full sync';

        $this->withToken($token)->postJson('/api/core/v1/me/overtime/requests', [
            'date' => today()->next(Carbon::TUESDAY)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reason' => $reason,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.hours', 2);
        $this->withToken($token)->getJson('/api/core/v1/me/overtime/requests')
            ->assertOk()
            ->assertJsonFragment(['reason' => $reason]);

        $overtime = OvertimeRequest::where('employee_id', $employee->id)->where('reason', $reason)->firstOrFail();
        $this->assertSame('submitted', $overtime->status);
        $this->assertGreaterThan(0, (float) $overtime->calculated_amount);
        $this->assertDatabaseHas('approval_requests', [
            'document_type' => 'overtime',
            'reference_id' => $overtime->id,
            'status' => 'submitted',
        ]);
        $this->actingAs($superAdmin)->get(route('overtime.index'))
            ->assertOk()
            ->assertSee($reason);
    }

    private function verifyPayrollReadModelInCore(string $token, Company $company, Employee $employee): void
    {
        $this->assertDatabaseHas('payslips', [
            'employee_id' => $employee->id,
            'status' => 'published',
        ]);

        $this->withToken($token)->getJson('/api/core/v1/me/bootstrap')
            ->assertOk()
            ->assertJsonPath('summary.latest_payslip.period', 'Juli 2026')
            ->assertJsonPath('user.company_id', $company->id);
    }

    private function syncGenericModuleRecordsFromCore(string $token, Employee $employee, User $superAdmin): void
    {
        $modules = [
            'claim' => 'claims',
            'loan' => 'loans',
            'document' => 'documents',
            'learning' => 'learning',
            'knowledge' => 'knowledge',
            'innovation' => 'innovation',
            'performance' => 'performance',
            'recruitment' => 'recruitment',
        ];

        foreach ($modules as $coreModule => $hcisRouteKey) {
            $title = 'Dummy Core '.ucwords(str_replace('_', ' ', $coreModule));
            $this->withToken($token)->postJson("/api/core/v1/me/modules/{$coreModule}/records", [
                'title' => $title,
                'record_date' => today()->toDateString(),
                'amount' => 10000,
                'description' => "Data dummy {$coreModule} dari Core.",
                'external_id' => "core-test-{$coreModule}",
                'metadata' => ['scenario' => 'full-core-sync'],
            ])->assertCreated()
                ->assertJsonPath('data.module', HcisAccess::moduleRecordKey($coreModule))
                ->assertJsonPath('data.title', $title)
                ->assertJsonPath('data.details.source', 'core');

            $this->withToken($token)->getJson("/api/core/v1/me/modules/{$coreModule}/records")
                ->assertOk()
                ->assertJsonFragment(['title' => $title]);

            $this->assertDatabaseHas('module_records', [
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'module' => HcisAccess::moduleRecordKey($coreModule),
                'title' => $title,
                'status' => 'submitted',
            ]);

            $this->actingAs($superAdmin)->get(route('modules.index', $hcisRouteKey))
                ->assertOk()
                ->assertSee($title);
        }

        $this->assertSame(count($modules), ModuleRecord::where('employee_id', $employee->id)->whereJsonContains('details->source', 'core')->count());
    }

    private function verifyReadOnlyCoreModulesRejectRecordWrites(string $token): void
    {
        foreach (['people', 'payroll'] as $module) {
            $this->withToken($token)->postJson("/api/core/v1/me/modules/{$module}/records", [
                'title' => "Read-only {$module}",
            ])->assertNotFound();
        }

        $this->withToken($token)->postJson('/api/core/v1/me/modules/bpjs/records', [
            'title' => 'Read-only BPJS',
        ])->assertForbidden();
    }
}
