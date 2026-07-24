<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CoreAccessRole;
use App\Models\SolutionModule;
use Illuminate\Database\Seeder;

class CoreAccessSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            ['people', 'People', 'Profil Saya', 'Data profil dan informasi pekerjaan', 'user-round', 10],
            ['attendance', 'Attendance', 'Kehadiran', 'Check-in, check-out, dan riwayat kehadiran', 'fingerprint', 20],
            ['leave', 'Leave', 'Cuti', 'Saldo, pengajuan, dan riwayat cuti', 'plane', 30],
            ['overtime', 'Overtime', 'Lembur', 'Pengajuan dan riwayat lembur', 'clock-3', 40],
            ['payroll', 'Payroll', 'Slip Gaji', 'Slip gaji dan informasi benefit pribadi', 'receipt-text', 50],
            ['claim', 'Reimbursement', 'Reimbursement', 'Pengajuan dan status penggantian biaya', 'wallet-cards', 52],
            ['loan', 'Loan', 'Pinjaman', 'Plafon, pengajuan, dan cicilan pinjaman', 'hand-coins', 54],
            ['document', 'Document Management', 'Dokumen', 'Surat, formulir, dan dokumen pribadi', 'file-text', 56],
            ['bpjs', 'Benefit & BPJS', 'Kesejahteraan', 'Kepesertaan BPJS dan benefit karyawan', 'heart-pulse', 58],
            ['learning', 'Learning Management System', 'Learning', 'Program, course, assessment, dan sertifikat', 'graduation-cap', 60],
            ['knowledge', 'Knowledge Management System', 'Knowledge', 'Artikel, dokumen, dan knowledge sharing', 'library', 70],
            ['innovation', 'Innovation Management System', 'Innovation', 'Campaign dan pengajuan ide', 'lightbulb', 80],
            ['performance', 'Performance Management', 'Performance', 'KPI, appraisal, dan feedback', 'chart-no-axes-combined', 90],
            ['recruitment', 'Recruitment', 'Career', 'Lowongan internal dan referral', 'briefcase-business', 100],
        ];

        foreach ($catalog as [$key, $name, $label, $description, $icon, $order]) {
            SolutionModule::updateOrCreate(
                ['solution' => 'hcis', 'key' => $key],
                ['name' => $name, 'core_label' => $label, 'description' => $description, 'icon' => $icon, 'sort_order' => $order, 'is_active' => true]
            );
        }

        Company::query()->each(function (Company $company) {
            $modules = SolutionModule::where('solution', 'hcis')->get();
            $company->solutionModules()->syncWithoutDetaching(
                $modules->mapWithKeys(fn ($module) => [$module->id => ['is_active' => true]])->all()
            );

            $definitions = [
                'HCIS-BASE' => ['name' => 'HCIS Employee Base', 'modules' => ['people']],
                'HCIS-TIME' => ['name' => 'HCIS Time Employee', 'modules' => ['attendance', 'leave', 'overtime']],
                'HCIS-PAYROLL' => ['name' => 'HCIS Payroll Employee', 'modules' => ['payroll']],
                'HCIS-LMS' => ['name' => 'HCIS LMS Learner', 'modules' => ['learning']],
                'HCIS-KMS' => ['name' => 'HCIS KMS Reader', 'modules' => ['knowledge']],
                'HCIS-IMS' => ['name' => 'HCIS Innovation Participant', 'modules' => ['innovation']],
                'HCIS-PERFORMANCE' => ['name' => 'HCIS Performance Employee', 'modules' => ['performance']],
                'HCIS-ALL' => ['name' => 'HCIS All Employee Services', 'modules' => $modules->pluck('key')->all()],
            ];

            foreach ($definitions as $code => $definition) {
                $role = CoreAccessRole::updateOrCreate(
                    ['company_id' => $company->id, 'code' => $code],
                    ['name' => $definition['name'], 'description' => 'Role akses layanan HCIS di Ensys Core', 'is_active' => true]
                );
                $role->modules()->sync(
                    $modules->whereIn('key', $definition['modules'])->mapWithKeys(function ($module) {
                        $abilities = match ($module->key) {
                            'attendance' => ['view', 'check_in', 'check_out', 'request_correction'],
                            'leave', 'overtime', 'innovation', 'claim', 'loan', 'document', 'recruitment' => ['view', 'create'],
                            'knowledge' => ['view', 'contribute'],
                            'learning' => ['view', 'consume'],
                            'performance' => ['view', 'update'],
                            default => ['view'],
                        };

                        return [$module->id => ['abilities' => json_encode($abilities)]];
                    })->all()
                );
            }

            // Preserve the access that legacy Core users had before module-based access existed.
            // Administrators can narrow it immediately from HCIS > Core Access.
            $allRole = CoreAccessRole::where('company_id', $company->id)->where('code', 'HCIS-ALL')->first();
            $company->employees()->where('status', 'active')->each(function ($employee) use ($allRole) {
                if ($employee->coreAccessRoles()->doesntExist()) {
                    $employee->coreAccessRoles()->attach($allRole->id, ['scope_type' => 'self']);
                }
            });
        });
    }
}
