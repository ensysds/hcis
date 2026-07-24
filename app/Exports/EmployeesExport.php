<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly array $companyIds = []) {}

    public function collection()
    {
        return Employee::with(['company', 'department', 'position', 'manager', 'children'])->when($this->companyIds, fn ($query) => $query->whereIn('company_id', $this->companyIds))->orderBy('nrp')->get();
    }

    public function headings(): array
    {
        $headings = [
            'NRP', 'NAMA', 'POSISI', 'ORGANIZATIONAL UNIT', 'PT', 'SBU', 'DIREKTORAT', 'DIVISI', 'DEPARTEMEN', 'SECTION', 'CORE SUPPORT', 'LOKASI KERJA', 'LANTAI', 'PENEMPATAN', 'PERSONNEL AREA', 'SUBAREA', 'EMPLOYEE GROUP', 'GOLONGAN', 'PANGKAT', 'POH', 'POH TYPE', 'STATUS KARYAWAN', 'TANGGAL PEREKRUTAN', 'MULAI KONTRAK', 'AKHIR KONTRAK', 'MASA KONTRAK', 'TANGGAL SK. TETAP', 'MASA PENGANGKATAN TETAP', 'TANGGAL KELUAR', 'NRP ATASAN', 'NAMA ATASAN', 'MASA PENSIUN', 'PENDIDIKAN', 'ASAL SEKOLAH', 'JURUSAN', 'TAHUN LULUS', 'ALAMAT TINGGAL', 'KOTA TINGGAL', 'PROVINSI TINGGAL', 'KODE POS TINGGAL', 'ALAMAT SURAT', 'KOTA SURAT', 'PROVINSI SURAT', 'KODE POS SURAT', 'NO.TELP.RMH', 'NO.HP', 'E-MAIL KANTOR', 'E-MAIL PRIBADI', 'TMPT LAHIR', 'TGL.LAHIR', 'GENERASI', 'JENIS KELAMIN', 'AGAMA', 'BPJS KETENAGAKERJAAN', 'BPJS KESEHATAN', 'NPWP', 'NOMOR KTP', 'NOMOR KK', 'BANK KEY', 'BANK ACCOUNT', 'PAYEE', 'PAYMENT METHOD', 'GOLONGAN DARAH', 'RHESUS', 'TINGGI BADAN', 'BERAT BADAN', 'UKURAN BAJU', 'UKURAN CELANA', 'UKURAN SEPATU', 'STATUS PERNIKAHAN', 'STS PAJAK', 'TANGGAL NIKAH', 'NAMA PASANGAN', 'TEMPAT LHR PASANGAN', 'TGL.LHR PASANGAN',
        ];
        for ($i = 1; $i <= 7; $i++) {
            array_push($headings, "NAMA ANAK KE-$i", "JENIS KELAMIN ANAK KE-$i", "TGL.LAHIR ANAK KE-$i", "TEMPAT LAHIR ANAK KE-$i");
        }
        return array_merge($headings, [
            'NAMA AYAH', 'TGL.LAHIR AYAH', 'TEMPAT LAHIR AYAH', 'NAMA IBU', 'TGL.LAHIR IBU', 'TEMPAT LAHIR IBU', 'NAMA AYAH MERTUA', 'TGL.LAHIR AYAH MERTUA', 'TEMPAT LAHIR AYAH MERTUA', 'NAMA IBU MERTUA', 'TGL.LAHIR IBU MERTUA', 'TEMPAT LAHIR IBU MERTUA', 'NAME EMERGENCY CONTACT', 'JENIS KELAMIN EMERGENCY CONTACT', 'NUMBER EMERGENCY CONTACT', 'ALAMAT EMERGENCY CONTACT', 'HUBUNGAN',
        ]);
    }

    public function map($e): array
    {
        $gender = fn ($value) => ['M' => 'Laki-laki', 'F' => 'Perempuan'][$value] ?? null;
        $date = fn ($value) => $value?->format('Y-m-d');
        $children = $e->children->keyBy('sequence');
        $row = [
            $e->nrp, $e->full_name, $e->position?->name, $e->organizational_unit, $e->company?->name ?? $e->pt, $e->sbu, $e->directorate, $e->division, $e->department?->name, $e->section, $e->core_support, $e->work_location, $e->floor, $e->placement, $e->personnel_area, $e->subarea, $e->employee_group, $e->grade, $e->rank, $e->poh, $e->poh_type, $e->employment_status, $date($e->join_date), $date($e->contract_start_date), $date($e->contract_end_date), $e->contract_duration, $date($e->permanent_appointment_date), $e->permanent_tenure, $date($e->exit_date), $e->manager?->nrp, $e->manager?->full_name, $date($e->retirement_date), $e->education_level, $e->school_name, $e->major, $e->graduation_year, $e->address, $e->residence_city, $e->residence_province, $e->residence_postal_code, $e->mailing_address, $e->mailing_city, $e->mailing_province, $e->mailing_postal_code, $e->home_phone, $e->phone, $e->email, $e->personal_email, $e->birth_place, $date($e->birth_date), $e->generation, $gender($e->gender), $e->religion, $e->bpjs_employment_number, $e->bpjs_health_number, $e->npwp, $e->nik, $e->family_card_number, $e->bank_key, $e->bank_account, $e->payee, $e->payment_method, $e->blood_type, $e->rhesus, $e->height_cm, $e->weight_kg, $e->shirt_size, $e->pants_size, $e->shoe_size, $e->marital_status, $e->tax_status, $date($e->marriage_date), $e->spouse_name, $e->spouse_birth_place, $date($e->spouse_birth_date),
        ];
        for ($i = 1; $i <= 7; $i++) {
            $child = $children->get($i);
            array_push($row, $child?->name, $gender($child?->gender), $date($child?->birth_date), $child?->birth_place);
        }
        return array_merge($row, [
            $e->father_name, $date($e->father_birth_date), $e->father_birth_place, $e->mother_name, $date($e->mother_birth_date), $e->mother_birth_place, $e->father_in_law_name, $date($e->father_in_law_birth_date), $e->father_in_law_birth_place, $e->mother_in_law_name, $date($e->mother_in_law_birth_date), $e->mother_in_law_birth_place, $e->emergency_contact_name, $gender($e->emergency_contact_gender), $e->emergency_contact_phone, $e->emergency_contact_address, $e->emergency_contact_relationship,
        ]);
    }
}
