<x-app-layout>
    <x-slot:title>{{ $title }}</x-slot:title>
    <x-page-header :title="$title" subtitle="Kelola proses, status, dan arsip modul secara terpadu.">
        <x-slot:actions>
            @can($permissionKey.'.create')
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newRecord"><i class="bi bi-plus"></i> Data Baru</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if($module === 'reports' && auth()->user()->can('report.export'))
        <div class="mb-3">
            <a class="btn btn-outline-success btn-sm" href="{{ route('reports.employees.excel') }}">Export Karyawan XLSX</a>
            <a class="btn btn-outline-danger btn-sm" href="{{ route('reports.employees.pdf') }}">Export Karyawan PDF</a>
        </div>
    @endif

    <x-card>
        <x-data-table>
            <thead>
                <tr>
                    <th>Referensi</th>
                    <th>Perusahaan</th>
                    <th>Judul</th>
                    <th>Karyawan</th>
                    <th>Tanggal</th>
                    <th>Nilai</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $record)
                    <tr>
                        <td class="fw-semibold">{{ $record->reference_no }}</td>
                        <td>{{ $record->company?->code ?? '-' }}</td>
                        <td>
                            {{ $record->title }}
                            @if(data_get($record->details, 'description'))
                                <div class="text-secondary">{{ Str::limit(data_get($record->details, 'description'), 55) }}</div>
                            @endif
                        </td>
                        <td>{{ $record->employee?->full_name ?? '-' }}</td>
                        <td>{{ $record->record_date?->format('d M Y') ?? '-' }}</td>
                        <td>{{ $record->amount !== null ? 'Rp '.number_format($record->amount, 0, ',', '.') : '-' }}</td>
                        <td><x-status-badge :status="$record->status"/></td>
                        <td class="text-end">
                            @can($permissionKey.'.delete')
                                <form method="post" action="{{ route('modules.destroy', [$module, $record]) }}" onsubmit="return confirm('Arsipkan data ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state"><i class="bi bi-folder2-open fs-3 d-block mb-2"></i>Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </x-data-table>
        <div class="mt-3">{{ $records->links() }}</div>
    </x-card>

    @can($permissionKey.'.create')
        <x-modal id="newRecord" title="Data Baru - {{ $title }}">
            <form method="post" action="{{ route('modules.store', $module) }}">
                @csrf
                <x-form-input name="title" label="Judul / Nama" required class="mb-3"/>
                <x-form-select name="company_id" label="Perusahaan" :options="$companies->mapWithKeys(fn($company) => [$company->id => $company->code.' · '.$company->name])" class="mb-3"/>
                @if(auth()->user()->hasAnyRole(['super_admin','admin_hr']))
                    <x-form-select name="employee_id" label="Karyawan Terkait" :options="$employees->mapWithKeys(fn($employee) => [$employee->id => $employee->nrp.' · '.$employee->full_name])" class="mb-3"/>
                @endif
                <x-form-input name="record_date" label="Tanggal" type="date" class="mb-3"/>
                <x-form-input name="amount" label="Nilai / Nominal" type="number" min="0" class="mb-3"/>
                <x-form-select name="status" label="Status" :options="['draft'=>'Draft','submitted'=>'Submitted','in_review'=>'In Review','approved'=>'Approved','completed'=>'Completed','active'=>'Active','inactive'=>'Inactive']" value="draft" required class="mb-3"/>
                <div class="mb-3"><label class="form-label">Keterangan</label><textarea class="form-control" name="description"></textarea></div>
                <div class="text-end"><button class="btn btn-primary">Simpan</button></div>
            </form>
        </x-modal>
    @endcan
</x-app-layout>
