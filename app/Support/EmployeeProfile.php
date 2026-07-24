<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class EmployeeProfile
{
    public static function sections(): array
    {
        return [
            'identity' => ['title' => 'Identitas & Kontak', 'icon' => 'person-vcard', 'fields' => [
                self::f('nrp', 'NRP', required: true, col: 3),
                self::f('full_name', 'Nama Lengkap', required: true, col: 5),
                self::f('nik', 'Nomor KTP', col: 4),
                self::f('family_card_number', 'Nomor KK', col: 4),
                self::f('gender', 'Jenis Kelamin', 'select', self::genderOptions(), col: 4),
                self::f('religion', 'Agama', 'select', self::religionOptions(), col: 4),
                self::f('birth_place', 'Tempat Lahir', col: 4),
                self::f('birth_date', 'Tanggal Lahir', 'date', required: true, col: 4),
                self::f('email', 'E-mail Kantor', 'email', required: true, col: 4),
                self::f('personal_email', 'E-mail Pribadi', 'email', col: 4),
                self::f('phone', 'No. HP', 'tel', col: 4),
                self::f('home_phone', 'No. Telepon Rumah', 'tel', col: 4),
            ]],
            'organization' => ['title' => 'Organisasi & Penempatan', 'icon' => 'diagram-3', 'fields' => [
                self::source('company_id', 'Perusahaan', 'companies', required: true, col: 4),
                self::source('position_id', 'Posisi', 'positions', col: 4),
                self::f('organizational_unit', 'Organizational Unit', 'suggest', col: 4),
                self::f('sbu', 'SBU', 'suggest', col: 4),
                self::f('directorate', 'Direktorat', 'suggest', col: 4),
                self::f('division', 'Divisi', 'suggest', col: 4),
                self::source('department_id', 'Departemen', 'departments', col: 4),
                self::f('section', 'Section', 'suggest', col: 4),
                self::f('core_support', 'Core / Support', 'select', ['core' => 'Core', 'support' => 'Support'], col: 4),
                self::f('work_location', 'Lokasi Kerja', 'suggest', col: 4),
                self::f('floor', 'Lantai', col: 4),
                self::f('placement', 'Penempatan', 'suggest', col: 4),
                self::f('personnel_area', 'Personnel Area', 'suggest', col: 4),
                self::f('subarea', 'Subarea', 'suggest', col: 4),
                self::f('employee_group', 'Employee Group', 'select', self::employeeGroupOptions(), col: 4),
                self::f('grade', 'Golongan', 'suggest', col: 4),
                self::f('rank', 'Pangkat', 'suggest', col: 4),
                self::f('poh', 'POH', 'suggest', col: 4),
                self::f('poh_type', 'POH Type', 'select', ['local' => 'Local', 'non_local' => 'Non Local'], col: 4),
                self::source('manager_id', 'Atasan', 'managers', col: 4),
            ]],
            'employment' => ['title' => 'Status & Masa Kerja', 'icon' => 'briefcase', 'fields' => [
                self::f('employment_status', 'Status Karyawan', 'select', self::employmentStatusOptions(), required: true, col: 4),
                self::f('status', 'Status Data', 'select', ['active' => 'Aktif', 'inactive' => 'Nonaktif', 'resigned' => 'Keluar'], required: true, col: 4),
                self::f('join_date', 'Tanggal Perekrutan', 'date', required: true, col: 4),
                self::f('contract_start_date', 'Mulai Kontrak', 'date', col: 4),
                self::f('contract_end_date', 'Akhir Kontrak', 'date', col: 4),
                self::f('permanent_appointment_date', 'Tanggal SK Tetap', 'date', col: 4),
                self::f('exit_date', 'Tanggal Keluar', 'date', col: 4),
                self::f('retirement_date', 'Masa / Tanggal Pensiun', 'date', col: 4),
            ]],
            'education' => ['title' => 'Pendidikan Terakhir', 'icon' => 'mortarboard', 'fields' => [
                self::f('education_level', 'Pendidikan', 'select', self::educationOptions(), col: 3),
                self::f('school_name', 'Asal Sekolah / Institusi', col: 4),
                self::f('major', 'Jurusan', col: 3),
                self::f('graduation_year', 'Tahun Lulus', 'integer', col: 2, min: 1940, max: 2100),
            ]],
            'address' => ['title' => 'Alamat', 'icon' => 'geo-alt', 'fields' => [
                self::f('address', 'Alamat Tinggal', 'textarea', col: 12),
                self::f('residence_city', 'Kota Tinggal', 'suggest', col: 4),
                self::f('residence_province', 'Provinsi Tinggal', 'suggest', col: 4),
                self::f('residence_postal_code', 'Kode Pos Tinggal', col: 4),
                self::f('mailing_address', 'Alamat Surat', 'textarea', col: 12),
                self::f('mailing_city', 'Kota Surat', 'suggest', col: 4),
                self::f('mailing_province', 'Provinsi Surat', 'suggest', col: 4),
                self::f('mailing_postal_code', 'Kode Pos Surat', col: 4),
            ]],
            'legal' => ['title' => 'Dokumen & Jaminan Sosial', 'icon' => 'shield-check', 'fields' => [
                self::f('bpjs_employment_number', 'BPJS Ketenagakerjaan', col: 3),
                self::f('bpjs_health_number', 'BPJS Kesehatan', col: 3),
                self::f('npwp', 'NPWP', col: 3),
                self::f('tax_status', 'Status Pajak', 'select', self::taxStatusOptions(), col: 3),
            ]],
            'bank' => ['title' => 'Informasi Pembayaran', 'icon' => 'bank', 'fields' => [
                self::f('bank_key', 'Bank Key / Kode Bank', col: 3),
                self::f('bank_account', 'Bank Account', col: 3),
                self::f('payee', 'Payee / Nama Rekening', col: 3),
                self::f('payment_method', 'Payment Method', 'select', ['transfer' => 'Transfer Bank', 'cash' => 'Tunai', 'other' => 'Lainnya'], col: 3),
            ]],
            'physical' => ['title' => 'Data Fisik', 'icon' => 'activity', 'fields' => [
                self::f('blood_type', 'Golongan Darah', 'select', ['A' => 'A', 'B' => 'B', 'AB' => 'AB', 'O' => 'O'], col: 2),
                self::f('rhesus', 'Rhesus', 'select', ['positive' => 'Positif (+)', 'negative' => 'Negatif (-)'], col: 2),
                self::f('height_cm', 'Tinggi Badan (cm)', 'number', col: 2, min: 50, max: 250, step: '0.01'),
                self::f('weight_kg', 'Berat Badan (kg)', 'number', col: 2, min: 20, max: 300, step: '0.01'),
                self::f('shirt_size', 'Ukuran Baju', 'select', array_combine(['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'], ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL']), col: 2),
                self::f('pants_size', 'Ukuran Celana', col: 2),
                self::f('shoe_size', 'Ukuran Sepatu', 'number', col: 2, min: 20, max: 55, step: '0.5'),
            ]],
            'marriage' => ['title' => 'Pernikahan & Pasangan', 'icon' => 'heart', 'fields' => [
                self::f('marital_status', 'Status Pernikahan', 'select', self::maritalStatusOptions(), col: 3),
                self::f('marriage_date', 'Tanggal Nikah', 'date', col: 3),
                self::f('spouse_name', 'Nama Pasangan', col: 3),
                self::f('spouse_birth_place', 'Tempat Lahir Pasangan', col: 3),
                self::f('spouse_birth_date', 'Tanggal Lahir Pasangan', 'date', col: 3),
            ]],
            'parents' => ['title' => 'Orang Tua & Mertua', 'icon' => 'people', 'fields' => [
                self::f('father_name', 'Nama Ayah', col: 4), self::f('father_birth_place', 'Tempat Lahir Ayah', col: 4), self::f('father_birth_date', 'Tanggal Lahir Ayah', 'date', col: 4),
                self::f('mother_name', 'Nama Ibu', col: 4), self::f('mother_birth_place', 'Tempat Lahir Ibu', col: 4), self::f('mother_birth_date', 'Tanggal Lahir Ibu', 'date', col: 4),
                self::f('father_in_law_name', 'Nama Ayah Mertua', col: 4), self::f('father_in_law_birth_place', 'Tempat Lahir Ayah Mertua', col: 4), self::f('father_in_law_birth_date', 'Tanggal Lahir Ayah Mertua', 'date', col: 4),
                self::f('mother_in_law_name', 'Nama Ibu Mertua', col: 4), self::f('mother_in_law_birth_place', 'Tempat Lahir Ibu Mertua', col: 4), self::f('mother_in_law_birth_date', 'Tanggal Lahir Ibu Mertua', 'date', col: 4),
            ]],
            'emergency' => ['title' => 'Kontak Darurat', 'icon' => 'telephone-forward', 'fields' => [
                self::f('emergency_contact_name', 'Nama Emergency Contact', col: 4),
                self::f('emergency_contact_gender', 'Jenis Kelamin', 'select', self::genderOptions(), col: 2),
                self::f('emergency_contact_phone', 'Nomor Emergency Contact', 'tel', col: 3),
                self::f('emergency_contact_relationship', 'Hubungan', 'select', self::relationshipOptions(), col: 3),
                self::f('emergency_contact_address', 'Alamat', 'textarea', col: 12),
            ]],
        ];
    }

    public static function rules(?int $employeeId = null): array
    {
        $rules = [];
        foreach (self::sections() as $section) {
            foreach ($section['fields'] as $field) {
                $name = $field['name'];
                $base = $field['required'] ? ['required'] : ['nullable'];
                $base[] = match ($field['type']) {
                    'date' => 'date', 'email' => 'email:rfc', 'integer' => 'integer', 'number' => 'numeric',
                    default => $field['type'] === 'textarea' ? 'string' : 'string',
                };
                if (in_array($field['type'], ['text', 'suggest', 'email', 'tel'], true)) {
                    $base[] = 'max:255';
                }
                if ($field['type'] === 'textarea') {
                    $base[] = 'max:2000';
                }
                if (isset($field['min'])) {
                    $base[] = 'min:'.$field['min'];
                }
                if (isset($field['max'])) {
                    $base[] = 'max:'.$field['max'];
                }
                if ($field['type'] === 'select' && $field['options']) {
                    $base[] = Rule::in(array_keys($field['options']));
                }
                $rules[$name] = $base;
            }
        }

        $rules['nrp'] = ['required', 'string', 'max:20', Rule::unique('employees', 'nrp')->ignore($employeeId)];
        $rules['nik'] = ['nullable', 'string', 'max:30', Rule::unique('employees', 'nik')->ignore($employeeId)];
        $rules['email'] = ['required', 'email:rfc', 'max:150', Rule::unique('employees', 'email')->ignore($employeeId)];
        $rules['company_id'] = ['required', 'integer', 'exists:companies,id'];
        $rules['department_id'] = ['nullable', 'integer', 'exists:departments,id'];
        $rules['position_id'] = ['nullable', 'integer', 'exists:positions,id'];
        $rules['manager_id'] = ['nullable', 'integer', 'exists:employees,id', Rule::notIn(array_filter([$employeeId]))];
        $rules['contract_end_date'][] = 'after_or_equal:contract_start_date';
        $rules['exit_date'][] = 'after_or_equal:join_date';
        $rules['graduation_year'][] = 'digits:4';
        $rules['children'] = ['nullable', 'array', 'max:7'];
        $rules['children.*.name'] = ['nullable', 'string', 'max:255', 'required_with:children.*.gender,children.*.birth_date,children.*.birth_place'];
        $rules['children.*.gender'] = ['nullable', Rule::in(array_keys(self::genderOptions()))];
        $rules['children.*.birth_date'] = ['nullable', 'date'];
        $rules['children.*.birth_place'] = ['nullable', 'string', 'max:255'];

        return $rules;
    }

    public static function allFields(): array
    {
        return collect(self::sections())->flatMap(fn ($section) => $section['fields'])->values()->all();
    }

    public static function dateFields(): array
    {
        return collect(self::allFields())->where('type', 'date')->pluck('name')->all();
    }

    public static function suggestionFields(): array
    {
        return collect(self::allFields())->where('type', 'suggest')->pluck('name')->all();
    }

    public static function genderOptions(): array { return ['M' => 'Laki-laki', 'F' => 'Perempuan']; }
    public static function religionOptions(): array { return ['islam' => 'Islam', 'protestant' => 'Kristen Protestan', 'catholic' => 'Katolik', 'hindu' => 'Hindu', 'buddhist' => 'Buddha', 'confucian' => 'Konghucu', 'other' => 'Lainnya']; }
    public static function maritalStatusOptions(): array { return ['single' => 'Belum Menikah', 'married' => 'Menikah', 'divorced' => 'Cerai Hidup', 'widowed' => 'Cerai Mati']; }
    public static function employmentStatusOptions(): array { return ['permanent' => 'Tetap', 'contract' => 'Kontrak / PKWT', 'probation' => 'Probation', 'outsourced' => 'Outsource', 'intern' => 'Magang', 'daily' => 'Harian']; }
    public static function employeeGroupOptions(): array { return ['executive' => 'Executive', 'management' => 'Management', 'staff' => 'Staff', 'non_staff' => 'Non Staff', 'outsourced' => 'Outsource']; }
    public static function educationOptions(): array { return ['SD' => 'SD', 'SMP' => 'SMP', 'SMA/SMK' => 'SMA / SMK', 'D1' => 'Diploma 1', 'D2' => 'Diploma 2', 'D3' => 'Diploma 3', 'D4' => 'Diploma 4', 'S1' => 'Sarjana (S1)', 'S2' => 'Magister (S2)', 'S3' => 'Doktor (S3)']; }
    public static function taxStatusOptions(): array { return array_combine(['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'], ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3']); }
    public static function relationshipOptions(): array { return ['spouse' => 'Pasangan', 'parent' => 'Orang Tua', 'child' => 'Anak', 'sibling' => 'Saudara', 'relative' => 'Kerabat', 'other' => 'Lainnya']; }

    private static function f(string $name, string $label, string $type = 'text', array $options = [], bool $required = false, int $col = 4, int|float|null $min = null, int|float|null $max = null, string|null $step = null): array
    {
        return compact('name', 'label', 'type', 'options', 'required', 'col', 'min', 'max', 'step');
    }

    private static function source(string $name, string $label, string $source, bool $required = false, int $col = 4): array
    {
        return self::f($name, $label, 'source', required: $required, col: $col) + ['source' => $source];
    }
}
