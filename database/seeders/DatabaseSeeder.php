<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\ModuleRecord;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\User;
use App\Support\FixedSuperadmin;
use App\Support\HcisAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! app()->environment('testing')) {
            HcisAccess::ensurePermissions();
            $super = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
            $super->syncPermissions(Permission::all());
            $this->ensureFixedSuperadminAccount($super);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        HcisAccess::ensureDefaultModuleRoles();
        $super = Role::firstOrCreate(['name' => 'super_admin']);
        $super->syncPermissions(Permission::all());
        $hr = Role::firstOrCreate(['name' => 'admin_hr']);
        $hr->syncPermissions(Permission::whereNotIn('name', ['settings.delete', 'role.delete'])->get());
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions(Permission::whereIn('name', ['attendance.view', 'leave.view', 'leave.approve', 'overtime.view', 'overtime.approve', 'claim.view', 'claim.approve', 'performance.view', 'performance.update', 'performance.approve', 'approval.view', 'approval.approve'])->get());
        $employeeRole = Role::firstOrCreate(['name' => 'employee']);
        $employeeRole->syncPermissions([]);
        $company = Company::create(['code' => 'DEMO', 'name' => 'PT Nusantara Human Capital', 'tax_number' => '01.234.567.8-999.000', 'address' => 'Jakarta, Indonesia']);
        $locationId = DB::table('locations')->insertGetId(['company_id' => $company->id, 'code' => 'JKT-HO', 'name' => 'Jakarta Head Office', 'address' => 'Jakarta', 'created_at' => now(), 'updated_at' => now()]);
        $gradeIds = [];
        foreach ([['G1', 'Staff', 1, 5000000, 8000000], ['G2', 'Supervisor', 2, 8000000, 14000000], ['G3', 'Manager', 3, 14000000, 25000000]] as $g) {
            $gradeIds[] = DB::table('job_grades')->insertGetId(['code' => $g[0], 'name' => $g[1], 'level' => $g[2], 'min_salary' => $g[3], 'max_salary' => $g[4], 'created_at' => now(), 'updated_at' => now()]);
        }
        $dept = [];
        foreach ([['HCD', 'Human Capital'], ['FIN', 'Finance'], ['OPS', 'Operations']] as $d) {
            $dept[$d[0]] = Department::create(['company_id' => $company->id, 'code' => $d[0], 'name' => $d[1]]);
        }
        $positions = [];
        foreach ([['HRM', 'HR Manager', 'HCD', 2], ['HRO', 'HR Officer', 'HCD', 1], ['FNM', 'Finance Manager', 'FIN', 2], ['FNO', 'Finance Officer', 'FIN', 1], ['OPM', 'Operations Manager', 'OPS', 2], ['OPO', 'Operations Staff', 'OPS', 1]] as $p) {
            $positions[$p[0]] = Position::create(['department_id' => $dept[$p[2]]->id, 'job_grade_id' => $gradeIds[$p[3] - 1], 'code' => $p[0], 'name' => $p[1], 'headcount' => 5]);
        }
        $names = ['Raka Pratama', 'Dewi Anggraini', 'Bima Saputra', 'Sari Wulandari', 'Andi Wijaya', 'Nadia Putri', 'Fajar Nugroho', 'Lina Kartika', 'Arif Hidayat', 'Maya Lestari', 'Dimas Setiawan', 'Rina Oktavia', 'Yoga Permana', 'Ayu Maharani', 'Rizky Ramadhan'];
        $employees = [];
        foreach ($names as $i => $name) {
            $nrp = str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT);
            $deptCode = $i < 5 ? 'HCD' : ($i < 9 ? 'FIN' : 'OPS');
            $posCode = $i === 0 ? 'HRM' : ($i === 2 ? 'FNM' : ($i === 4 ? 'OPM' : ($deptCode === 'HCD' ? 'HRO' : ($deptCode === 'FIN' ? 'FNO' : 'OPO'))));
            $employees[$i] = Employee::create(['nrp' => $nrp, 'nik' => '317300'.str_pad((string) $i, 10, '0', STR_PAD_LEFT), 'full_name' => $name, 'email' => match ($i) {
                0 => 'superadmin@demo.com',1 => 'hr@demo.com',2 => 'manager@demo.com',3 => 'employee@demo.com',default => 'employee'.($i + 1).'@demo.com'
            }, 'phone' => '08120000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'gender' => $i % 2 ? 'F' : 'M', 'birth_place' => 'Jakarta', 'birth_date' => now()->subYears(25 + $i % 12)->subDays($i * 13), 'address' => 'Jakarta', 'marital_status' => $i % 3 ? 'single' : 'married', 'employment_status' => 'permanent', 'join_date' => now()->subYears(1 + $i % 8), 'company_id' => $company->id, 'department_id' => $dept[$deptCode]->id, 'position_id' => $positions[$posCode]->id, 'job_grade_id' => $gradeIds[$posCode[-1] === 'M' ? 2 : 0] ?? $gradeIds[0], 'location_id' => $locationId, 'status' => 'active']);
        }
        $employees[2]->update(['manager_id' => $employees[0]->id]);
        for ($i = 3; $i < count($employees); $i++) {
            $employees[$i]->update(['manager_id' => $employees[2]->id]);
        }
        $demo = [['superadmin@demo.com', '00001', 'super_admin'], ['hr@demo.com', '00002', 'admin_hr'], ['manager@demo.com', '00003', 'manager']];
        foreach ($demo as $i => $d) {
            $u = User::create(['employee_id' => $employees[$i]->id, 'name' => $employees[$i]->full_name, 'nrp' => $d[1], 'email' => $d[0], 'password' => Hash::make('password'), 'is_active' => true, 'email_verified_at' => now()]);
            $u->assignRole($d[2]);
            $u->companies()->attach($company->id, ['access_level' => 'company']);
        }
        $annual = LeaveType::create(['code' => 'ANNUAL', 'name' => 'Cuti Tahunan', 'annual_quota' => 12, 'deduct_balance' => true]);
        $sick = LeaveType::create(['code' => 'SICK', 'name' => 'Cuti Sakit', 'annual_quota' => 12, 'requires_attachment' => true, 'deduct_balance' => false]);
        foreach ($employees as $e) {
            LeaveBalance::create(['employee_id' => $e->id, 'leave_type_id' => $annual->id, 'year' => now()->year, 'entitled' => 12, 'used' => 0]);
            LeaveBalance::create(['employee_id' => $e->id, 'leave_type_id' => $sick->id, 'year' => now()->year, 'entitled' => 12, 'used' => 0]);
        }
        DB::table('shifts')->insert(['company_id' => $company->id, 'code' => 'REG', 'name' => 'Regular Office', 'start_time' => '08:00', 'end_time' => '17:00', 'late_tolerance_minutes' => 10, 'created_at' => now(), 'updated_at' => now()]);
        $basic = DB::table('salary_components')->insertGetId(['company_id' => $company->id, 'code' => 'BASIC', 'name' => 'Gaji Pokok', 'type' => 'earning', 'is_taxable' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $transport = DB::table('salary_components')->insertGetId(['company_id' => $company->id, 'code' => 'TRANS', 'name' => 'Tunjangan Transport', 'type' => 'earning', 'is_taxable' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $bpjs = DB::table('salary_components')->insertGetId(['company_id' => $company->id, 'code' => 'BPJS', 'name' => 'Potongan BPJS', 'type' => 'deduction', 'is_taxable' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($employees as $i => $e) {
            foreach ([[$basic, 6000000 + $i * 250000], [$transport, 750000], [$bpjs, 250000]] as $s) {
                DB::table('employee_salaries')->insert(['employee_id' => $e->id, 'salary_component_id' => $s[0], 'amount' => $s[1], 'effective_date' => now()->startOfYear(), 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        PayrollPeriod::create(['company_id' => $company->id, 'name' => now()->subMonth()->translatedFormat('F Y'), 'start_date' => now()->subMonth()->startOfMonth(), 'end_date' => now()->subMonth()->endOfMonth(), 'pay_date' => now()->subMonth()->endOfMonth(), 'status' => 'draft']);
        foreach (['recruitment', 'onboarding', 'performance', 'learning', 'assets', 'documents'] as $i => $module) {
            ModuleRecord::create(['company_id' => $company->id, 'module' => $module, 'reference_no' => strtoupper(substr($module, 0, 3)).'-DEMO-'.($i + 1), 'title' => ['Backend Developer', 'Onboarding Batch Juli', 'Mid Year Review', 'Leadership Essentials', 'Laptop Dell Latitude', 'Surat Keterangan Kerja'][$i], 'employee_id' => $i ? $employees[min($i + 2, 14)]->id : null, 'record_date' => today()->addDays($i), 'status' => $i % 2 ? 'active' : 'draft', 'details' => ['description' => 'Data contoh untuk pengujian modul.']]);
        }
        DB::table('system_settings')->insert([['key' => 'company_name', 'value' => $company->name, 'type' => 'string', 'created_at' => now(), 'updated_at' => now()], ['key' => 'timezone', 'value' => 'Asia/Jakarta', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()]]);
        $this->call(CoreAccessSeeder::class);
    }

    private function ensureFixedSuperadminAccount(Role $superRole): void
    {
        $company = Company::firstOrCreate(
            ['code' => 'ENSYS'],
            ['name' => 'PT Ensys Digital Solusi', 'tax_number' => null, 'address' => 'Indonesia']
        );
        $department = Department::firstOrCreate(
            ['code' => 'SYS-HC'],
            ['company_id' => $company->id, 'name' => 'Human Capital System']
        );
        $position = Position::firstOrCreate(
            ['code' => 'SYSADMIN'],
            ['department_id' => $department->id, 'name' => 'Super Administrator', 'headcount' => 1]
        );
        $this->call(CoreAccessSeeder::class);

        $employee = Employee::where('email', FixedSuperadmin::EMAIL)->first()
            ?: Employee::where('nrp', FixedSuperadmin::NRP)->first()
            ?: new Employee();
        $employee->forceFill([
            'email' => FixedSuperadmin::EMAIL,
            'nrp' => $employee->nrp ?: FixedSuperadmin::NRP,
            'nik' => $employee->nik,
            'full_name' => 'Super Administrator',
            'phone' => $employee->phone,
            'gender' => $employee->gender,
            'birth_place' => $employee->birth_place ?: 'Jakarta',
            'birth_date' => $employee->birth_date ?: '1990-01-01',
            'address' => $employee->address ?: 'Indonesia',
            'marital_status' => $employee->marital_status ?: 'single',
            'employment_status' => 'permanent',
            'join_date' => $employee->join_date ?: today(),
            'company_id' => $company->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'status' => 'active',
            'core_role' => 'director',
            'core_password' => FixedSuperadmin::passwordHash(),
            'core_is_active' => true,
            'core_must_change_password' => false,
            'core_password_reset_requested_at' => null,
        ])->save();

        $allRole = $company->coreAccessRoles()->where('code', 'HCIS-ALL')->first();
        if ($allRole) {
            $employee->coreAccessRoles()->syncWithoutDetaching([$allRole->id => ['scope_type' => 'self']]);
        }

        $user = User::where('email', FixedSuperadmin::EMAIL)->first()
            ?: User::where('nrp', FixedSuperadmin::NRP)->first()
            ?: new User();
        $user->forceFill([
            'email' => FixedSuperadmin::EMAIL,
            'employee_id' => $employee->id,
            'name' => 'Super Administrator',
            'nrp' => FixedSuperadmin::NRP,
            'password' => FixedSuperadmin::passwordHash(),
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();
        $user->syncRoles([$superRole->name]);
        $user->companies()->syncWithoutDetaching([$company->id => ['access_level' => 'company']]);
    }
}
