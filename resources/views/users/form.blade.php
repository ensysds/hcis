<x-app-layout>
    <x-slot:title>{{ $account->exists ? 'Edit User' : 'User Baru' }}</x-slot:title>
    <x-page-header :title="$account->exists ? 'Edit User' : 'User Baru'" subtitle="Atur akun login, status aktif, dan role yang melekat ke user.">
        <x-slot:actions>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
        </x-slot:actions>
    </x-page-header>

    <form method="post" action="{{ $account->exists ? route('users.update', $account) : route('users.store') }}">
        @csrf
        @if($account->exists)@method('PUT')@endif

        <div class="row g-3">
            <div class="col-lg-5">
                <x-card title="Akun">
                    <div class="row g-3">
                        <x-form-input name="name" label="Nama User" :value="$account->name" required class="col-12"/>
                        <x-form-input name="email" label="Email Login" type="email" :value="$account->email" required class="col-12"/>
                        <x-form-input name="nrp" label="NRP Login" :value="$account->nrp" required class="col-md-6"/>
                        <x-form-input name="password" :label="$account->exists ? 'Password Back-office Baru' : 'Password Back-office'" type="password" :required="!$account->exists" class="col-md-6"/>
                        <x-form-input name="password_confirmation" label="Konfirmasi Password" type="password" :required="!$account->exists" class="col-md-6"/>
                        <div class="col-12">
                            <input type="hidden" name="is_active" value="0">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->exists ? $account->is_active : true))>
                                <span class="form-check-label">User aktif dan boleh login</span>
                            </label>
                        </div>
                    </div>
                </x-card>
                <x-card title="Akses Perusahaan" class="mt-3">
                    <div class="small text-secondary mb-2">Pilih perusahaan yang boleh dikelola user ini. Super admin otomatis dapat melihat semua perusahaan.</div>
                    <select name="company_ids[]" class="form-select form-select-sm" multiple size="6">
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected(in_array((string) $company->id, array_map('strval', old('company_ids', $selectedCompanies)), true))>{{ $company->code }} · {{ $company->name }}</option>
                        @endforeach
                    </select>
                    @error('company_ids')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    @error('company_ids.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </x-card>
            </div>

            <div class="col-lg-7">
                <x-card title="Role Assignment">
                    <div class="small text-secondary mb-3">Pilih satu atau lebih role. Jika permission antar-role beririsan, sistem akan menggabungkannya.</div>
                    <div class="role-grid">
                        @foreach($roles as $role)
                            <label class="role-option">
                                <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->name }}" @checked(in_array($role->name, old('roles', $selectedRoles), true))>
                                <span>
                                    <strong>{{ $role->name }}</strong>
                                    <small>
                                        @if($role->name === 'super_admin')
                                            System role
                                        @elseif(str_starts_with($role->name, 'module_'))
                                            Default modul
                                        @else
                                            Custom role
                                        @endif
                                    </small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('roles')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </x-card>
            </div>
        </div>

        <div class="text-end">
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Batal</a>
            <button class="btn btn-primary">Simpan User</button>
        </div>
    </form>
</x-app-layout>
