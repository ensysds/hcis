<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\CoreAccessRole;
use App\Models\Attendance;
use App\Models\User;
use Database\Seeders\CoreAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CoreApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_login_session_and_logout_flow(): void
    {
        $company = Company::create(['code' => 'CORE', 'name' => 'PT Core Test']);
        $employee = Employee::create([
            'full_name' => 'Andi Core',
            'nrp' => '00012345',
            'email' => 'andi@core.test',
            'join_date' => '2026-07-17',
            'birth_date' => '1990-01-02',
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $login = $this->postJson('/api/core/v1/auth/login', [
            'login' => '00012345',
            'password' => '2345170726020190',
            'device_name' => 'Test Browser',
        ])->assertOk()->assertJsonPath('user.email', 'andi@core.test');

        $token = $login->json('token');

        $this->withToken($token)->getJson('/api/core/v1/auth/session')
            ->assertOk()
            ->assertJsonPath('user.nrp', '00012345');

        $this->withToken($token)->postJson('/api/core/v1/auth/logout')->assertOk();
        $this->withToken($token)->getJson('/api/core/v1/auth/session')->assertUnauthorized();
        $this->assertDatabaseMissing('core_api_tokens', ['employee_id' => $employee->id]);
    }

    public function test_core_employee_can_login_with_email_without_hcis_admin_user(): void
    {
        $company = Company::create(['code' => 'CORE', 'name' => 'PT Core Test']);
        Employee::create([
            'full_name' => 'Email Core',
            'nrp' => '00054321',
            'email' => 'email.core@test.local',
            'join_date' => '2026-07-17',
            'birth_date' => '1990-01-02',
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'email.core@test.local']);

        $this->postJson('/api/core/v1/auth/login', [
            'login' => 'email.core@test.local',
            'password' => '4321170726020190',
        ])->assertOk()->assertJsonPath('user.email', 'email.core@test.local');
    }

    public function test_core_login_rejects_invalid_credentials(): void
    {
        $company = Company::create(['code' => 'CORE', 'name' => 'PT Core Test']);
        Employee::create([
            'full_name' => 'Andi Core',
            'nrp' => '00012345',
            'email' => 'andi@core.test',
            'join_date' => '2026-07-17',
            'birth_date' => '1990-01-02',
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $this->postJson('/api/core/v1/auth/login', [
            'login' => '00012345',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_core_login_rejects_inactive_employee(): void
    {
        $company = Company::create(['code' => 'CORE', 'name' => 'PT Core Test']);
        Employee::create([
            'full_name' => 'Andi Core',
            'nrp' => '00012345',
            'email' => 'andi@core.test',
            'join_date' => '2026-07-17',
            'birth_date' => '1990-01-02',
            'company_id' => $company->id,
            'status' => 'inactive',
        ]);

        $this->postJson('/api/core/v1/auth/login', [
            'login' => '00012345',
            'password' => '2345170726020190',
        ])->assertUnprocessable();
    }

    public function test_core_bootstrap_only_returns_modules_from_company_and_employee_roles(): void
    {
        $company = Company::create(['code' => 'PTA', 'name' => 'PT A']);
        $employee = Employee::create([
            'full_name' => 'Learner PTA', 'nrp' => '00077777', 'email' => 'learner@pta.test',
            'join_date' => '2026-07-17', 'birth_date' => '1990-01-02',
            'company_id' => $company->id, 'status' => 'active',
        ]);
        $this->seed(CoreAccessSeeder::class);
        $lmsRole = CoreAccessRole::where('company_id', $company->id)->where('code', 'HCIS-LMS')->firstOrFail();
        $employee->coreAccessRoles()->sync([$lmsRole->id => ['scope_type' => 'self']]);

        $token = $this->postJson('/api/core/v1/auth/login', [
            'login' => '00077777', 'password' => '7777170726020190',
        ])->assertOk()->json('token');

        $bootstrap = $this->withToken($token)->getJson('/api/core/v1/me/bootstrap')->assertOk();
        $this->assertSame(['learning'], collect($bootstrap->json('modules'))->pluck('key')->all());
        $this->withToken($token)->getJson('/api/core/v1/me/attendance')->assertForbidden();
    }

    public function test_core_attendance_is_written_to_hcis_when_time_role_is_active(): void
    {
        $company = Company::create(['code' => 'TIME', 'name' => 'PT Time']);
        $employee = Employee::create([
            'full_name' => 'Time Employee', 'nrp' => '00088888', 'email' => 'time@employee.test',
            'join_date' => '2026-07-17', 'birth_date' => '1990-01-02',
            'company_id' => $company->id, 'status' => 'active',
        ]);
        $this->seed(CoreAccessSeeder::class);
        $timeRole = CoreAccessRole::where('company_id', $company->id)->where('code', 'HCIS-TIME')->firstOrFail();
        $employee->coreAccessRoles()->sync([$timeRole->id => ['scope_type' => 'self']]);
        $token = $this->postJson('/api/core/v1/auth/login', [
            'login' => '00088888', 'password' => '8888170726020190',
        ])->assertOk()->json('token');

        $this->withToken($token)->postJson('/api/core/v1/me/attendance/check-in', [
            'latitude' => -6.2, 'longitude' => 106.8,
        ])->assertOk()->assertJsonPath('data.status', 'present');
        $this->assertTrue(Attendance::where('employee_id', $employee->id)->whereDate('date', today())->exists());

        $this->withToken($token)->postJson('/api/core/v1/me/attendance/check-out')->assertOk();
        $this->assertNotNull($employee->refresh()->core_last_login_at);
    }

    public function test_core_module_record_is_written_to_hcis_and_visible_from_hcis_alias_page(): void
    {
        $company = Company::create(['code' => 'SYNC', 'name' => 'PT Sync']);
        $employee = Employee::create([
            'full_name' => 'Core Claim Employee', 'nrp' => '00099999', 'email' => 'claim@employee.test',
            'join_date' => '2026-07-17', 'birth_date' => '1990-01-02',
            'company_id' => $company->id, 'status' => 'active',
        ]);
        $this->seed(CoreAccessSeeder::class);
        $token = $this->postJson('/api/core/v1/auth/login', [
            'login' => '00099999', 'password' => '9999170726020190',
        ])->assertOk()->json('token');

        $this->withToken($token)->postJson('/api/core/v1/me/modules/claim/records', [
            'title' => 'Klaim parkir dari Core',
            'record_date' => '2026-07-20',
            'amount' => 25000,
            'description' => 'Dikirim dari Ensys Core.',
        ])->assertCreated()
            ->assertJsonPath('data.module', 'claim')
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('module_records', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'module' => 'claim',
            'title' => 'Klaim parkir dari Core',
            'status' => 'submitted',
        ]);

        $superAdminRole = Role::create(['name' => 'super_admin']);
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'nrp' => 'SUPER-SYNC',
            'email' => 'super.sync@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $superAdmin->assignRole($superAdminRole);

        $this->actingAs($superAdmin)->get(route('modules.index', 'claims'))
            ->assertOk()
            ->assertSee('Klaim parkir dari Core');
    }
}
