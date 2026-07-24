<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAction;
use App\Models\EmploymentContract;
use App\Models\HcCase;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnterpriseBackofficeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_enterprise_backoffice_pages_render(): void
    {
        $user = User::where('email', 'superadmin@demo.com')->firstOrFail();

        foreach (['enterprise.lifecycle', 'enterprise.cases', 'enterprise.policies', 'enterprise.assets', 'enterprise.talent', 'enterprise.reports', 'enterprise.time', 'enterprise.payroll-setup'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }

    public function test_contract_and_employee_action_update_master_data_with_history(): void
    {
        $hr = User::where('email', 'hr@demo.com')->firstOrFail();
        $employee = Employee::where('email', 'employee@demo.com')->firstOrFail();

        $this->actingAs($hr)->post(route('enterprise.contracts.store'), [
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'contract_number' => 'PKWT-2026-001',
            'contract_type' => 'fixed_term',
            'start_date' => '2026-08-01',
            'end_date' => '2027-07-31',
        ])->assertSessionHas('success');

        $contract = EmploymentContract::where('contract_number', 'PKWT-2026-001')->firstOrFail();
        $this->actingAs($hr)->post(route('enterprise.contracts.approve', $contract))->assertSessionHas('success');
        $this->assertSame('active', $contract->fresh()->status);
        $this->assertSame('fixed_term', $employee->fresh()->employment_status);

        $targetDepartment = Department::where('company_id', $employee->company_id)->whereKeyNot($employee->department_id)->firstOrFail();
        $this->actingAs($hr)->post(route('enterprise.actions.store'), [
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'action_type' => 'transfer',
            'effective_date' => '2026-09-01',
            'to_department_id' => $targetDepartment->id,
            'reason' => 'Rotasi organisasi',
        ])->assertSessionHas('success');

        $action = EmployeeAction::latest()->firstOrFail();
        $this->actingAs($hr)->post(route('enterprise.actions.approve', $action))->assertSessionHas('success');
        $this->assertSame($targetDepartment->id, $employee->fresh()->department_id);
        $this->assertSame('approved', $action->fresh()->status);
    }

    public function test_hc_case_requires_checklist_completion_before_close(): void
    {
        $hr = User::where('email', 'hr@demo.com')->firstOrFail();
        $employee = Employee::where('email', 'employee@demo.com')->firstOrFail();
        $this->actingAs($hr)->post(route('enterprise.cases.store'), [
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'case_type' => 'offboarding',
            'subject' => 'Clearance karyawan',
            'priority' => 'high',
            'opened_date' => '2026-07-16',
        ])->assertSessionHas('success');
        $case = HcCase::firstOrFail();
        $this->actingAs($hr)->post(route('enterprise.cases.tasks', $case), ['title' => 'Pengembalian laptop'])->assertSessionHas('success');
        $this->actingAs($hr)->post(route('enterprise.cases.close', $case))->assertSessionHasErrors('case');
        $task = $case->tasks()->firstOrFail();
        $this->actingAs($hr)->post(route('enterprise.case-tasks.complete', $task))->assertSessionHas('success');
        $this->actingAs($hr)->post(route('enterprise.cases.close', $case))->assertSessionHas('success');
        $this->assertSame('closed', $case->fresh()->status);
    }

    public function test_company_scope_prevents_cross_entity_employee_access(): void
    {
        $primary = Company::firstOrFail();
        $other = Company::create(['code' => 'OTHER', 'name' => 'PT Other', 'is_active' => true]);
        $outsiderEmployee = Employee::create([
            'nrp' => 'OTH001', 'full_name' => 'Karyawan Entitas Lain', 'email' => 'other@example.com',
            'join_date' => today(), 'company_id' => $other->id, 'status' => 'active',
        ]);
        $user = User::create(['name' => 'Scoped HC', 'nrp' => 'SCOPE1', 'email' => 'scope@example.com', 'password' => Hash::make('password'), 'is_active' => true]);
        $user->assignRole('admin_hr');
        $user->companies()->attach($primary->id, ['access_level' => 'company']);

        $this->actingAs($user)->get(route('employees.index'))->assertOk()->assertDontSee('Karyawan Entitas Lain');
        $this->actingAs($user)->get(route('employees.show', $outsiderEmployee))->assertForbidden();
    }

    public function test_time_administration_shift_roster_and_attendance_correction_flow(): void
    {
        $hr = User::where('email', 'hr@demo.com')->firstOrFail();
        $employee = Employee::where('email', 'employee@demo.com')->firstOrFail();

        $this->actingAs($hr)->post(route('enterprise.shifts.store'), [
            'company_id' => $employee->company_id,
            'code' => 'TST',
            'name' => 'Test Shift',
            'start_time' => '07:00',
            'end_time' => '16:00',
            'late_tolerance_minutes' => 5,
            'break_minutes' => 60,
        ])->assertSessionHas('success');

        $shift = Shift::where('code', 'TST')->firstOrFail();
        $this->actingAs($hr)->post(route('enterprise.schedules.store'), [
            'employee_ids' => [$employee->id],
            'shift_id' => $shift->id,
            'date_from' => '2026-07-20',
            'date_to' => '2026-07-20',
        ])->assertSessionHas('success');
        $this->assertTrue(ShiftSchedule::where('employee_id', $employee->id)->whereDate('date', '2026-07-20')->exists());

        $attendance = Attendance::create(['employee_id' => $employee->id, 'date' => '2026-07-20', 'check_in_at' => '2026-07-20 08:10:00', 'status' => 'present']);
        $this->actingAs($hr)->post(route('enterprise.corrections.store'), [
            'attendance_id' => $attendance->id,
            'requested_in' => '2026-07-20 07:58:00',
            'requested_out' => '2026-07-20 16:05:00',
            'reason' => 'Mesin absensi terlambat sinkron.',
        ])->assertSessionHas('success');

        $correction = AttendanceCorrection::firstOrFail();
        $this->actingAs($hr)->post(route('enterprise.corrections.approve', $correction), ['action' => 'approved'])->assertSessionHas('success');
        $this->assertSame('approved', $correction->fresh()->status);
        $this->assertSame('07:58:00', $attendance->fresh()->check_in_at->format('H:i:s'));
    }

    public function test_payroll_adjustment_is_included_when_period_runs(): void
    {
        $hr = User::where('email', 'hr@demo.com')->firstOrFail();
        $employee = Employee::where('email', 'employee@demo.com')->firstOrFail();
        $period = PayrollPeriod::where('company_id', $employee->company_id)->where('status', 'draft')->firstOrFail();

        $this->actingAs($hr)->post(route('enterprise.adjustments.store'), [
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'name' => 'Insentif Project',
            'type' => 'earning',
            'amount' => 500000,
            'reason' => 'Tambahan project kritikal.',
        ])->assertSessionHas('success');

        $this->actingAs($hr)->post(route('payroll.run', $period))->assertSessionHas('success');
        $payslip = Payslip::where('payroll_period_id', $period->id)->where('employee_id', $employee->id)->firstOrFail();
        $this->assertDatabaseHas('payslip_details', ['payslip_id' => $payslip->id, 'name' => 'Insentif Project', 'amount' => 500000]);
    }

    public function test_leave_attachment_is_private_and_downloadable_by_authorized_hc(): void
    {
        Storage::fake('local');
        $hr = User::where('email', 'hr@demo.com')->firstOrFail();
        $employee = Employee::where('email', 'employee@demo.com')->firstOrFail();
        $sick = \App\Models\LeaveType::where('code', 'SICK')->firstOrFail();

        $this->actingAs($hr)->post(route('leave.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $sick->id,
            'start_date' => today()->addWeekday()->toDateString(),
            'end_date' => today()->addWeekday()->toDateString(),
            'reason' => 'Sakit',
            'attachment' => UploadedFile::fake()->create('surat-dokter.pdf', 128, 'application/pdf'),
        ])->assertRedirect(route('leave.index'));

        $leave = LeaveRequest::latest()->firstOrFail();
        Storage::disk('local')->assertExists($leave->attachment_path);
        $this->actingAs($hr)->get(route('leave.attachment', $leave))->assertOk();
    }
}
