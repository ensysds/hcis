<x-app-layout>
    <x-slot:title>Profil Karyawan</x-slot:title>
    @if(!$employee)
        <div class="alert alert-warning">Akun ini belum terhubung dengan data karyawan.</div>
    @else
        <x-page-header :title="$employee->full_name" :subtitle="$employee->nrp.' · '.($employee->position?->name ?? 'Belum ada posisi')">
            <x-slot:actions>@can('employee.update')<a href="{{ route('employees.edit', $employee) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit Data Lengkap</a>@endcan</x-slot:actions>
        </x-page-header>

        <div class="row g-3">
            <div class="col-xl-3">
                <x-card>
                    <div class="text-center py-3"><div class="avatar mx-auto mb-2" style="width:64px;height:64px;font-size:24px;color:white">{{ strtoupper(substr($employee->full_name,0,1)) }}</div><h5 class="mb-1">{{ $employee->full_name }}</h5><div class="text-muted small mb-2">{{ $employee->nrp }}</div><x-status-badge :status="$employee->status"/></div>
                    <hr><dl class="row small mb-0"><dt class="col-5">PT</dt><dd class="col-7">{{ $employee->company?->name ?? $employee->pt ?? '-' }}</dd><dt class="col-5">Posisi</dt><dd class="col-7">{{ $employee->position?->name ?? '-' }}</dd><dt class="col-5">Departemen</dt><dd class="col-7">{{ $employee->department?->name ?? '-' }}</dd><dt class="col-5">E-mail</dt><dd class="col-7 text-break">{{ $employee->email }}</dd><dt class="col-5">No. HP</dt><dd class="col-7">{{ $employee->phone ?? '-' }}</dd></dl>
                </x-card>
                <x-card title="Akun Core" class="mt-3">
                    <dl class="row small mb-0">
                        <dt class="col-5">Role</dt><dd class="col-7">{{ $coreRoles[$employee->core_role] ?? $employee->core_role ?? 'Employee' }}</dd>
                        <dt class="col-5">Login Core</dt><dd class="col-7 text-break">{{ $employee->nrp }} / {{ $employee->email }}</dd>
                        <dt class="col-5">Akses</dt><dd class="col-7"><x-status-badge :status="$employee->canLoginToCore() ? 'active' : 'inactive'"/></dd>
                        <dt class="col-5">Login Terakhir</dt><dd class="col-7">{{ $employee->core_last_login_at?->translatedFormat('d M Y H:i') ?? '-' }}</dd>
                        <dt class="col-5">Reset</dt><dd class="col-7">{{ $employee->core_password_reset_requested_at?->translatedFormat('d M Y H:i') ?? '-' }}</dd>
                    </dl>
                    @can('employee.update')
                        <div class="d-grid gap-2 mt-3">
                            @if(auth()->user()->hasRole('super_admin'))
                                <div class="alert alert-info small py-2 mb-1 text-start">
                                    Password ini hanya untuk Core. Password HCIS Back Office tetap di menu User Management atau Profil & Password.
                                </div>
                                @if((int) session('core_password_updated_employee_id') === $employee->id)
                                    <div class="alert alert-success small py-2 mb-1 text-start">
                                        Password Core sudah tersimpan. Kolom di bawah kosong lagi karena password tidak ditampilkan ulang.
                                    </div>
                                @endif
                                <form method="post" action="{{ route('employees.core-password-update', $employee) }}" onsubmit="return confirm('Ganti password Core karyawan ini? Sesi Core aktif akan dicabut.')">
                                    @csrf
                                    @method('PUT')
                                    <div class="mb-2 text-start">
                                        <label class="form-label small" for="core_password">Password Core Baru</label>
                                        <input class="form-control form-control-sm" type="password" name="core_password" id="core_password" autocomplete="new-password" required minlength="8">
                                        @error('core_password')<div class="text-danger small">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="mb-2 text-start">
                                        <label class="form-label small" for="core_password_confirmation">Konfirmasi Password</label>
                                        <input class="form-control form-control-sm" type="password" name="core_password_confirmation" id="core_password_confirmation" autocomplete="new-password" required minlength="8">
                                    </div>
                                    <button class="btn btn-primary btn-sm w-100"><i class="bi bi-key"></i> Simpan Password Core</button>
                                </form>
                                <form method="post" action="{{ route('employees.core-password-reset', $employee) }}" onsubmit="return confirm('Reset password Core karyawan ini ke default?')">
                                    @csrf
                                    <button class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-arrow-clockwise"></i> Reset Password Core</button>
                                </form>
                            @else
                                <form method="post" action="{{ route('employees.core-password-reset-request', $employee) }}">
                                    @csrf
                                    <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-send"></i> Kirim Reset Password</button>
                                </form>
                            @endif
                        </div>
                    @endcan
                </x-card>
            </div>
            <div class="col-xl-9">
                @foreach($sections as $key => $section)
                    <x-card :title="$section['title']">
                        <dl class="profile-section-grid mb-0">
                            @foreach($section['fields'] as $field)
                                @php
                                    $name = $field['name']; $value = $employee->{$name};
                                    if ($name === 'company_id') $value = $employee->company?->name;
                                    if ($name === 'pt') $value = $employee->company?->name ?? $employee->pt;
                                    if ($name === 'department_id') $value = $employee->department?->name;
                                    if ($name === 'position_id') $value = $employee->position?->name;
                                    if ($name === 'manager_id') $value = $employee->manager ? $employee->manager->nrp.' · '.$employee->manager->full_name : null;
                                    if ($field['type'] === 'date' && $value) $value = $value->translatedFormat('d F Y');
                                    if ($field['type'] === 'select' && $value) $value = $field['options'][$value] ?? $value;
                                @endphp
                                <div class="profile-data-row"><dt>{{ $field['label'] }}</dt><dd>{{ filled($value) ? $value : '-' }}</dd></div>
                            @endforeach
                            @if($key === 'identity')<div class="profile-data-row"><dt>Generasi</dt><dd>{{ $employee->generation ?? '-' }}</dd></div>@endif
                            @if($key === 'employment')
                                <div class="profile-data-row"><dt>Masa Kontrak</dt><dd>{{ $employee->contract_duration ?? '-' }}</dd></div>
                                <div class="profile-data-row"><dt>Masa Pengangkatan Tetap</dt><dd>{{ $employee->permanent_tenure ?? '-' }}</dd></div>
                                <div class="profile-data-row"><dt>NRP Atasan</dt><dd>{{ $employee->manager?->nrp ?? '-' }}</dd></div>
                                <div class="profile-data-row"><dt>Nama Atasan</dt><dd>{{ $employee->manager?->full_name ?? '-' }}</dd></div>
                            @endif
                        </dl>
                    </x-card>
                @endforeach

                <x-card title="Data Anak">
                    @if($employee->children->isEmpty())
                        <div class="text-muted small">Belum ada data anak.</div>
                    @else
                        <div class="table-responsive"><table class="table hcis-table mb-0"><thead><tr><th>Ke-</th><th>Nama</th><th>Jenis Kelamin</th><th>Tanggal Lahir</th><th>Tempat Lahir</th></tr></thead><tbody>@foreach($employee->children as $child)<tr><td>{{ $child->sequence }}</td><td>{{ $child->name }}</td><td>{{ ['M'=>'Laki-laki','F'=>'Perempuan'][$child->gender] ?? '-' }}</td><td>{{ $child->birth_date?->translatedFormat('d F Y') ?? '-' }}</td><td>{{ $child->birth_place ?? '-' }}</td></tr>@endforeach</tbody></table></div>
                    @endif
                </x-card>
            </div>
        </div>
    @endif
</x-app-layout>
