<x-app-layout>
    <x-slot:title>{{ $employee->exists ? 'Edit Karyawan' : 'Karyawan Baru' }}</x-slot:title>
    <x-page-header :title="$employee->exists ? 'Edit Karyawan' : 'Karyawan Baru'" subtitle="Profil karyawan lengkap, terstruktur, dan siap untuk kebutuhan HC maupun payroll."/>

    <form method="post" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" id="employeeForm">
        @csrf
        @if($employee->exists) @method('PUT') @endif
        <div class="employee-form-layout">
            <div class="employee-form-nav">
                <div class="nav nav-pills flex-lg-column flex-nowrap" role="tablist">
                    @foreach($sections as $key => $section)
                        <button class="nav-link @if($loop->first) active @endif" data-bs-toggle="pill" data-bs-target="#section-{{ $key }}" type="button"><i class="bi bi-{{ $section['icon'] }}"></i><span>{{ $section['title'] }}</span></button>
                    @endforeach
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#section-core" type="button"><i class="bi bi-key"></i><span>Akun Core</span></button>
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#section-children" type="button"><i class="bi bi-people-fill"></i><span>Data Anak</span></button>
                </div>
            </div>
            <div class="tab-content employee-form-content">
                @foreach($sections as $key => $section)
                    <div class="tab-pane fade @if($loop->first) show active @endif" id="section-{{ $key }}">
                        <x-card :title="$section['title']">
                            <div class="row g-3">
                                @foreach($section['fields'] as $field)
                                    @php
                                        $name = $field['name']; $value = $employee->{$name};
                                        if ($field['type'] === 'date' && $value) $value = $value->format('Y-m-d');
                                        $inputClass = 'col-md-'.$field['col'];
                                    @endphp
                                    @if($field['type'] === 'select')
                                        <x-form-select :name="$name" :label="$field['label']" :options="$field['options']" :value="$value" :required="$field['required']" :class="$inputClass"/>
                                    @elseif($field['type'] === 'source')
                                        <x-form-select :name="$name" :label="$field['label']" :options="$sources[$field['source']] ?? []" :value="$value" :required="$field['required']" :class="$inputClass"/>
                                    @elseif($field['type'] === 'textarea')
                                        <div class="{{ $inputClass }}"><label class="form-label" for="{{ $name }}">{{ $field['label'] }}</label><textarea class="form-control form-control-sm" rows="2" name="{{ $name }}" id="{{ $name }}">{{ old($name, $value) }}</textarea>@error($name)<div class="text-danger small">{{ $message }}</div>@enderror</div>
                                    @else
                                        <x-form-input :name="$name" :label="$field['label']" :type="in_array($field['type'], ['suggest', 'integer']) ? ($field['type'] === 'integer' ? 'number' : 'text') : $field['type']" :value="$value" :required="$field['required']" :class="$inputClass" :list="$field['type'] === 'suggest' ? 'list-'.$name : null" :min="$field['min']" :max="$field['max']" :step="$field['step']"/>
                                        @if($field['type'] === 'suggest')<datalist id="list-{{ $name }}">@foreach($suggestions[$name] ?? [] as $suggestion)<option value="{{ $suggestion }}">@endforeach</datalist>@endif
                                    @endif
                                @endforeach
                            </div>
                            @if($key === 'organization')<div class="derived-field-note mt-3"><i class="bi bi-diagram-3"></i> Perusahaan, unit organisasi, dan posisi berasal dari master Company & Organization Management. Kombinasi yang tidak sesuai tidak dapat disimpan.</div>@endif
                            @if($key === 'employment')<div class="derived-field-note mt-3"><i class="bi bi-calculator"></i> Masa kontrak, masa pengangkatan tetap, NRP/nama atasan, dan generasi dihitung otomatis dari data tanggal serta relasi atasan.</div>@endif
                        </x-card>
                    </div>
                @endforeach
                <div class="tab-pane fade" id="section-core">
                    <x-card title="Akun Core">
                        <div class="row g-3">
                            <x-form-select name="core_role" label="Role Core" :options="$coreRoles" :value="$employee->core_role ?? 'employee'" required class="col-md-6"/>
                            <div class="col-md-6">
                                <label class="form-label d-block">Status Akses Core</label>
                                <input type="hidden" name="core_is_active" value="0">
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="core_is_active" value="1" @checked(old('core_is_active', $employee->exists ? $employee->core_is_active : true))>
                                    <span class="form-check-label">Karyawan boleh login ke Core</span>
                                </label>
                                @error('core_is_active')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="derived-field-note mt-3"><i class="bi bi-info-circle"></i> Akun Core dibuat otomatis dari NRP. Password default: 4 digit terakhir NRP + tanggal join (DDMMYY) + tanggal lahir (DDMMYY).</div>
                    </x-card>
                </div>
                <div class="tab-pane fade" id="section-children">
                    <x-card title="Data Anak (maksimal 7)">
                        <div class="table-responsive"><table class="table hcis-table align-middle mb-0 employee-child-table"><thead><tr><th style="width:45px">Ke-</th><th>Nama Anak</th><th style="width:170px">Jenis Kelamin</th><th style="width:175px">Tanggal Lahir</th><th>Tempat Lahir</th></tr></thead><tbody>
                            @for($i = 0; $i < 7; $i++)
                                @php($child = $employee->children->get($i))
                                <tr><td class="text-center fw-semibold">{{ $i + 1 }}</td><td><input class="form-control form-control-sm" name="children[{{ $i }}][name]" value="{{ old("children.$i.name", $child?->name) }}"></td><td><select class="form-select form-select-sm" name="children[{{ $i }}][gender]"><option value="">Pilih</option><option value="M" @selected(old("children.$i.gender", $child?->gender)==='M')>Laki-laki</option><option value="F" @selected(old("children.$i.gender", $child?->gender)==='F')>Perempuan</option></select></td><td><input type="date" class="form-control form-control-sm" name="children[{{ $i }}][birth_date]" value="{{ old("children.$i.birth_date", $child?->birth_date?->format('Y-m-d')) }}"></td><td><input class="form-control form-control-sm" name="children[{{ $i }}][birth_place]" value="{{ old("children.$i.birth_place", $child?->birth_place) }}"></td></tr>
                            @endfor
                        </tbody></table></div>
                    </x-card>
                </div>
            </div>
        </div>
        <div class="employee-form-actions"><a href="{{ route('employees.index') }}" class="btn btn-outline-secondary">Batal</a><button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Simpan Data Karyawan</button></div>
    </form>
    <x-slot:scripts><script>const invalid=document.querySelector('#employeeForm .text-danger');if(invalid){const pane=invalid.closest('.tab-pane');const trigger=pane&&document.querySelector(`[data-bs-target="#${pane.id}"]`);if(trigger)bootstrap.Tab.getOrCreateInstance(trigger).show();}</script></x-slot:scripts>
</x-app-layout>
