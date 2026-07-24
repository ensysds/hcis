<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\ModuleRecord;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HcisWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Email perusahaan');
    }

    public function test_login_requires_matching_email_and_password(): void
    {
        $this->post('/login', ['email' => 'hr@demo.com', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'hr@demo.com', 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs(User::where('email', 'hr@demo.com')->first());
    }

    public function test_employee_self_service_account_is_not_seeded(): void
    {
        $this->assertDatabaseHas('employees', ['email' => 'employee@demo.com']);
        $this->assertDatabaseMissing('users', ['email' => 'employee@demo.com']);
    }

    public function test_only_superadmin_can_delete_employee_records(): void
    {
        $employee = Employee::where('email', 'employee@demo.com')->firstOrFail();
        $hr = User::where('email', 'hr@demo.com')->firstOrFail();
        $superAdmin = User::where('email', 'superadmin@demo.com')->firstOrFail();

        $this->actingAs($hr)->delete(route('employees.destroy', $employee))->assertForbidden();
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'deleted_at' => null]);

        $this->actingAs($superAdmin)->delete(route('employees.destroy', $employee))->assertRedirect(route('employees.index'));
        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
    }

    public function test_hc_records_leave_for_employee_and_approval_deducts_balance(): void
    {
        $employee = Employee::where('email', 'employee@demo.com')->first();
        $employeeUser = $this->makeNonHcUser($employee);
        $hr = User::where('email', 'hr@demo.com')->first();
        $balance = LeaveBalance::where('employee_id', $employee->id)->whereHas('leaveType', fn ($q) => $q->where('code', 'ANNUAL'))->first();
        $before = $balance->used;
        $leaveStart = now()->addWeek()->startOfWeek();
        $leaveEnd = $leaveStart->copy()->addDay();
        $leavePayload = ['employee_id' => $employee->id, 'leave_type_id' => $balance->leave_type_id, 'start_date' => $leaveStart->toDateString(), 'end_date' => $leaveEnd->toDateString(), 'reason' => 'Keperluan keluarga'];
        $this->actingAs($employeeUser)->post('/leave', $leavePayload)->assertForbidden();
        $this->actingAs($hr)->post('/leave', $leavePayload)->assertRedirect(route('leave.index'));
        $leave = LeaveRequest::latest()->first();
        $this->assertSame('submitted', $leave->status);
        $this->assertSame($employee->id, $leave->employee_id);
        $approval = ApprovalRequest::where('document_type', 'leave')->where('reference_id', $leave->id)->first();
        $manager = User::where('email', 'manager@demo.com')->first();
        $this->actingAs($manager)->post(route('approvals.act', $approval), ['action' => 'approved'])->assertSessionHas('success');
        $this->assertSame('in_review', $leave->fresh()->status);
        $this->actingAs($hr)->post(route('approvals.act', $approval), ['action' => 'approved'])->assertSessionHas('success');
        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertEquals($before + 2, $balance->fresh()->used);
    }

    public function test_hr_can_run_payroll_and_download_payslip(): void
    {
        $hr = User::where('email', 'hr@demo.com')->first();
        $period = PayrollPeriod::first();
        $this->actingAs($hr)->post(route('payroll.run', $period))->assertSessionHas('success');
        $this->assertSame('finalized', $period->fresh()->status);
        $slip = Payslip::firstOrFail();
        $nonHcUser = $this->makeNonHcUser(Employee::where('email', 'employee@demo.com')->first());
        $this->actingAs($nonHcUser)->get(route('payslips.pdf', $slip))->assertForbidden();
        $this->actingAs($hr)->get(route('payslips.pdf', $slip))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_superadmin_can_create_dynamic_role_and_assign_multiple_roles_to_user(): void
    {
        $superAdmin = User::where('email', 'superadmin@demo.com')->first();
        $employee = $this->makeNonHcUser(Employee::where('email', 'employee@demo.com')->first());

        $this->actingAs($superAdmin)->post(route('roles.store'), [
            'name' => 'tax_bpjs_viewer',
            'permissions' => ['tax.view', 'bpjs.view'],
        ])->assertRedirect();

        $this->assertTrue(Role::where('name', 'tax_bpjs_viewer')->first()->hasPermissionTo('tax.view'));

        $this->actingAs($superAdmin)->put(route('users.update', $employee), [
            'employee_id' => $employee->employee_id,
            'name' => $employee->name,
            'email' => $employee->email,
            'nrp' => $employee->nrp,
            'is_active' => '1',
            'roles' => ['employee', 'tax_bpjs_viewer', 'module_learning'],
        ])->assertSessionHas('success');

        $employee->refresh();
        $this->assertTrue($employee->hasAllRoles(['employee', 'tax_bpjs_viewer', 'module_learning']));
        $this->assertTrue($employee->can('tax.view'));
        $this->assertTrue($employee->can('bpjs.view'));
        $this->assertTrue($employee->can('learning.create'));
        $this->assertFalse($employee->can('leave.create'));
        $this->assertFalse($employee->can('overtime.create'));
        $this->assertFalse($employee->can('attendance.create'));
    }

    public function test_superadmin_can_change_backoffice_user_password(): void
    {
        $superAdmin = User::where('email', 'superadmin@demo.com')->first();
        $hr = User::with(['companies', 'roles'])->where('email', 'hr@demo.com')->first();

        $this->actingAs($superAdmin)->put(route('users.update', $hr), [
            'name' => $hr->name,
            'email' => $hr->email,
            'nrp' => $hr->nrp,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
            'is_active' => '1',
            'roles' => $hr->roles->pluck('name')->all(),
            'company_ids' => $hr->companies->pluck('id')->all(),
        ])->assertSessionHas('success');

        $this->assertFalse(Hash::check('password', $hr->fresh()->password));

        $this->post(route('logout'));
        $this->post('/login', ['email' => 'hr@demo.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'hr@demo.com', 'password' => 'new-password-123'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_superadmin_can_set_custom_core_password_for_employee(): void
    {
        $superAdmin = User::where('email', 'superadmin@demo.com')->first();
        $employee = Employee::where('email', 'employee@demo.com')->first();
        $defaultPassword = $employee->defaultCorePassword();

        $this->postJson('/api/core/v1/auth/login', [
            'login' => $employee->email,
            'password' => $defaultPassword,
        ])->assertOk();

        $this->actingAs($superAdmin)->put(route('employees.core-password-update', $employee), [
            'core_password' => 'core-baru-123',
            'core_password_confirmation' => 'core-baru-123',
        ])->assertSessionHas('success');

        $employee->refresh();
        $this->assertFalse(Hash::check($defaultPassword, $employee->core_password));
        $this->assertFalse($employee->core_must_change_password);
        $this->postJson('/api/core/v1/auth/login', [
            'login' => $employee->email,
            'password' => $defaultPassword,
        ])->assertUnprocessable();
        $this->postJson('/api/core/v1/auth/login', [
            'login' => $employee->email,
            'password' => 'core-baru-123',
        ])->assertOk();
    }

    public function test_view_only_role_cannot_create_module_record(): void
    {
        $superAdmin = User::where('email', 'superadmin@demo.com')->first();
        $employee = $this->makeNonHcUser(Employee::where('email', 'employee@demo.com')->first());

        $this->actingAs($superAdmin)->post(route('roles.store'), [
            'name' => 'tax_viewer_only',
            'permissions' => ['tax.view'],
        ])->assertRedirect();

        $employee->syncRoles(['employee', 'tax_viewer_only']);

        $this->actingAs($employee)->get(route('modules.index', 'tax'))->assertOk();
        $this->actingAs($employee)->post(route('modules.store', 'tax'), [
            'title' => 'Data pajak uji',
            'status' => 'draft',
        ])->assertForbidden();
    }

    public function test_sidebar_groups_modules_by_function_and_hides_unauthorized_groups(): void
    {
        $employee = $this->makeNonHcUser(Employee::where('email', 'employee@demo.com')->first());
        $payrollViewer = Role::create(['name' => 'payroll_sidebar_viewer']);
        $payrollViewer->givePermissionTo('tax.view');
        $employee->syncRoles([$payrollViewer]);

        $this->actingAs($employee)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('PAYROLL')
            ->assertSee('PPh 21')
            ->assertDontSee('TALENT MANAGEMENT')
            ->assertDontSee('PEOPLE DEVELOPMENT')
            ->assertDontSee('ADMINISTRATOR');

        $superAdmin = User::where('email', 'superadmin@demo.com')->first();

        $this->actingAs($superAdmin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('ORGANIZATION DEVELOPMENT')
            ->assertSee('TALENT MANAGEMENT')
            ->assertSee('PEOPLE DEVELOPMENT')
            ->assertSee('ADMINISTRATOR')
            ->assertSee('User Management')
            ->assertSee('Role Management');
    }

    public function test_sidebar_only_marks_the_current_menu_active(): void
    {
        $superAdmin = User::where('email', 'superadmin@demo.com')->first();

        $lifecycle = $this->actingAs($superAdmin)->get(route('enterprise.lifecycle'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($lifecycle, 'class="nav-link active"'));
        $this->assertMatchesRegularExpression('/class="nav-link active"[^>]*>\s*<i class="bi bi-person-lines-fill"><\/i> Employee Lifecycle/', $lifecycle);
        $this->assertDoesNotMatchRegularExpression('/class="nav-link active"[^>]*>\s*<i class="bi bi-calendar3"><\/i> Shift & Schedule/', $lifecycle);

        $bpjs = $this->actingAs($superAdmin)->get(route('enterprise.payroll-setup', ['menu' => 'bpjs']))->assertOk()->getContent();
        $this->assertSame(1, substr_count($bpjs, 'class="nav-link active"'));
        $this->assertMatchesRegularExpression('/class="nav-link active"[^>]*>\s*<i class="bi bi-shield-check"><\/i> BPJS/', $bpjs);
        $this->assertDoesNotMatchRegularExpression('/class="nav-link active"[^>]*>\s*<i class="bi bi-calculator"><\/i> PPh 21/', $bpjs);
    }

    public function test_module_pages_read_core_and_hcis_record_keys_consistently(): void
    {
        $superAdmin = User::where('email', 'superadmin@demo.com')->first();
        $employee = Employee::where('email', 'employee@demo.com')->first();

        ModuleRecord::create([
            'company_id' => $employee->company_id,
            'module' => 'claim',
            'reference_no' => 'COR-CLAIM-TEST',
            'title' => 'Reimbursement Core Legacy',
            'employee_id' => $employee->id,
            'record_date' => today(),
            'status' => 'submitted',
            'details' => ['source' => 'core'],
        ]);

        $this->actingAs($superAdmin)->get(route('modules.index', 'claims'))
            ->assertOk()
            ->assertSee('Reimbursement Core Legacy');

        $this->actingAs($superAdmin)->post(route('modules.store', 'claims'), [
            'company_id' => $employee->company_id,
            'title' => 'Reimbursement dari HCIS',
            'status' => 'draft',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('module_records', [
            'company_id' => $employee->company_id,
            'module' => 'claim',
            'title' => 'Reimbursement dari HCIS',
        ]);
    }

    public function test_superadmin_can_manage_company_and_hierarchical_organization(): void
    {
        $superAdmin = User::where('email', 'superadmin@demo.com')->first();

        $this->actingAs($superAdmin)->post(route('companies.store'), [
            'code' => 'ENSYS',
            'name' => 'PT Ensys Solusi Indonesia',
            'legal_name' => 'PT Ensys Solusi Indonesia',
            'tax_number' => '01.999.888.7-123.000',
            'address' => 'Jakarta',
            'is_active' => '1',
        ])->assertRedirect(route('companies.index'));

        $company = \App\Models\Company::where('code', 'ENSYS')->firstOrFail();
        $this->actingAs($superAdmin)->get(route('companies.index'))->assertOk()->assertSee('PT Ensys Solusi Indonesia');
        $this->actingAs($superAdmin)->get(route('companies.edit', $company))->assertOk()->assertSee('NPWP Perusahaan');
        $this->actingAs($superAdmin)->post(route('organization.store'), [
            'company_id' => $company->id,
            'code' => 'ENS-DIR',
            'name' => 'Direktorat Utama',
            'type' => 'directorate',
            'is_active' => '1',
        ])->assertSessionHas('success');

        $root = \App\Models\Department::where('code', 'ENS-DIR')->firstOrFail();
        $this->actingAs($superAdmin)->post(route('organization.store'), [
            'company_id' => $company->id,
            'parent_id' => $root->id,
            'code' => 'ENS-HC',
            'name' => 'Human Capital',
            'type' => 'department',
            'is_active' => '1',
        ])->assertSessionHas('success');

        $this->actingAs($superAdmin)->get(route('organization.index', ['company_id' => $company->id]))
            ->assertOk()->assertSeeInOrder(['Direktorat Utama', 'Human Capital']);
    }

    public function test_organization_rejects_parent_from_another_company(): void
    {
        $superAdmin = User::where('email', 'superadmin@demo.com')->first();
        $other = \App\Models\Company::create(['code' => 'OTHER', 'name' => 'PT Lain', 'is_active' => true]);
        $foreignParent = \App\Models\Department::first();

        $this->actingAs($superAdmin)->post(route('organization.store'), [
            'company_id' => $other->id,
            'parent_id' => $foreignParent->id,
            'code' => 'INVALID',
            'name' => 'Unit Tidak Valid',
            'type' => 'department',
            'is_active' => '1',
        ])->assertSessionHasErrors('parent_id');
    }

    private function makeNonHcUser(Employee $employee): User
    {
        $user = User::create([
            'employee_id' => $employee->id,
            'name' => $employee->full_name,
            'nrp' => $employee->nrp,
            'email' => 'non-hc-'.$employee->email,
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('employee');

        return $user;
    }
}
