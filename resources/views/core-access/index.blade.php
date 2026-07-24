<x-app-layout>
    <x-slot:title>Core Access</x-slot:title>
    <x-page-header title="Core Access" subtitle="Atur modul HCIS yang tampil di Ensys Core berdasarkan perusahaan dan role karyawan."/>

    <x-card title="Perusahaan">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Perusahaan aktif</label>
                <select class="form-select form-select-sm" name="company_id">
                    @foreach($companies as $option)
                        <option value="{{ $option->id }}" @selected($company?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-outline-primary btn-sm">Tampilkan</button></div>
        </form>
    </x-card>

    @if($company)
        <div class="row g-3 mt-0">
            <div class="col-xl-6">
                <x-card title="Modul HCIS di Core">
                    <form method="post" action="{{ route('core-access.company-modules', $company) }}">
                        @csrf
                        <div class="list-group list-group-flush mb-3">
                            @foreach($modules as $module)
                                <label class="list-group-item px-0 d-flex gap-3 align-items-start">
                                    <input class="form-check-input mt-1" type="checkbox" name="modules[]" value="{{ $module->id }}" @checked(in_array($module->id, $activeModuleIds))>
                                    <span><strong>{{ $module->core_label }}</strong><small class="d-block text-secondary">{{ $module->description }}</small></span>
                                </label>
                            @endforeach
                        </div>
                        <button class="btn btn-primary btn-sm">Simpan Aktivasi Modul</button>
                    </form>
                </x-card>
            </div>

            <div class="col-xl-6">
                <x-card title="Role Core">
                    <div class="mb-3">
                        @foreach($roles as $role)
                            <div class="border-bottom py-2"><strong>{{ $role->code }}</strong><div>{{ $role->name }}</div><small class="text-secondary">{{ $role->modules->pluck('core_label')->join(', ') ?: 'Belum ada modul' }}</small></div>
                        @endforeach
                    </div>
                    <form method="post" action="{{ route('core-access.roles.store') }}" class="row g-2">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $company->id }}">
                        <div class="col-md-7"><label class="form-label">Nama role</label><input class="form-control form-control-sm" name="name" required placeholder="Contoh: LMS Employee PTA"></div>
                        <div class="col-md-5"><label class="form-label">Kode</label><input class="form-control form-control-sm" name="code" placeholder="LMS-PTA"></div>
                        <div class="col-12"><label class="form-label">Modul</label><div class="d-flex flex-wrap gap-3">@foreach($modules as $module)<label class="form-check"><input class="form-check-input" type="checkbox" name="modules[]" value="{{ $module->id }}"><span class="form-check-label">{{ $module->core_label }}</span></label>@endforeach</div></div>
                        <div class="col-12"><button class="btn btn-outline-primary btn-sm">Tambah Role</button></div>
                    </form>
                </x-card>
            </div>
        </div>

        <x-card title="Akses Karyawan" class="mt-3">
            <x-data-table>
                <thead><tr><th>Karyawan</th><th>Unit / Posisi</th><th>Role Core</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                    @forelse($employees as $employee)
                        <tr>
                            <td><strong>{{ $employee->full_name }}</strong><div class="text-secondary">{{ $employee->nrp }}</div></td>
                            <td>{{ $employee->department?->name ?? '-' }}<div class="text-secondary">{{ $employee->position?->name ?? '-' }}</div></td>
                            <td colspan="2">
                                <form method="post" action="{{ route('core-access.employee-roles', $employee) }}" class="d-flex align-items-center justify-content-between gap-3">
                                    @csrf
                                    <div class="d-flex flex-wrap gap-3">@foreach($roles as $role)<label class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($employee->coreAccessRoles->contains('id', $role->id))><span class="form-check-label">{{ $role->code }}</span></label>@endforeach</div>
                                    <button class="btn btn-link btn-sm">Simpan</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">Belum ada karyawan aktif.</td></tr>
                    @endforelse
                </tbody>
            </x-data-table>
        </x-card>
    @endif
</x-app-layout>
