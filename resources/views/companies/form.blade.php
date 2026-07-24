<x-app-layout>
    <x-slot:title>{{ $company->exists ? 'Edit Perusahaan' : 'Perusahaan Baru' }}</x-slot:title>
    <x-page-header :title="$company->exists ? 'Edit Perusahaan' : 'Perusahaan Baru'" subtitle="Identitas legal, perpajakan, dan kontak perusahaan."/>
    <form method="post" action="{{ $company->exists ? route('companies.update', $company) : route('companies.store') }}">@csrf @if($company->exists) @method('PUT') @endif
        <x-card title="Identitas Perusahaan"><div class="row g-3">
            <x-form-input name="code" label="Kode Perusahaan" :value="$company->code" required class="col-md-3"/>
            <x-form-input name="name" label="Nama Perusahaan" :value="$company->name" required class="col-md-4"/>
            <x-form-input name="legal_name" label="Nama Legal" :value="$company->legal_name" class="col-md-5"/>
            <x-form-input name="tax_number" label="NPWP Perusahaan" :value="$company->tax_number" class="col-md-4"/>
            <x-form-input name="registration_number" label="Nomor Registrasi / NIB" :value="$company->registration_number" class="col-md-4"/>
            <x-form-input name="tax_office" label="Kantor Pajak (KPP)" :value="$company->tax_office" class="col-md-4"/>
            <div class="col-12"><label class="form-label">Alamat</label><textarea name="address" rows="3" class="form-control form-control-sm">{{ old('address', $company->address) }}</textarea></div>
            <x-form-input name="phone" label="Telepon" :value="$company->phone" class="col-md-3"/>
            <x-form-input name="email" label="E-mail" type="email" :value="$company->email" class="col-md-3"/>
            <x-form-input name="website" label="Website" type="url" :value="$company->website" class="col-md-3"/>
            <x-form-select name="is_active" label="Status" :options="[1 => 'Aktif', 0 => 'Nonaktif']" :value="$company->exists ? (int)$company->is_active : 1" required class="col-md-3"/>
        </div></x-card>
        <div class="text-end"><a href="{{ route('companies.index') }}" class="btn btn-outline-secondary">Batal</a> <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Simpan Perusahaan</button></div>
    </form>
</x-app-layout>
