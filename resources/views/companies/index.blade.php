<x-app-layout>
    <x-slot:title>Company Management</x-slot:title>
    <x-page-header title="Company Management" subtitle="Master legal entity sebagai fondasi struktur organisasi dan data karyawan.">
        <x-slot:actions>@can('company.create')<a href="{{ route('companies.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Perusahaan Baru</a>@endcan</x-slot:actions>
    </x-page-header>
    <x-card>
        <form class="row g-2 mb-3"><div class="col-md-6"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Cari nama, kode perusahaan, atau NPWP"></div><div class="col-md-3"><button class="btn btn-outline-primary btn-sm">Cari</button></div></form>
        <x-data-table><thead><tr><th>Kode</th><th>Nama Perusahaan</th><th>NPWP / KPP</th><th>Alamat</th><th>Struktur</th><th>Karyawan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
            @forelse($companies as $company)<tr>
                <td class="fw-semibold">{{ $company->code }}</td><td>{{ $company->name }}@if($company->legal_name)<div class="text-secondary">{{ $company->legal_name }}</div>@endif</td>
                <td>{{ $company->tax_number ?: '-' }}<div class="text-secondary">{{ $company->tax_office ?: '-' }}</div></td><td>{{ Str::limit($company->address, 45) ?: '-' }}</td>
                <td><a href="{{ route('organization.index', ['company_id' => $company->id]) }}">{{ $company->departments_count }} unit</a></td><td>{{ $company->employees_count }}</td>
                <td><span class="badge {{ $company->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $company->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                <td class="text-end">@can('company.update')<a class="btn btn-sm btn-link" href="{{ route('companies.edit', $company) }}" title="Edit"><i class="bi bi-pencil"></i></a>@endcan @can('company.delete')<form class="d-inline" method="post" action="{{ route('companies.destroy', $company) }}" onsubmit="return confirm('Arsipkan perusahaan ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger" title="Arsipkan"><i class="bi bi-archive"></i></button></form>@endcan</td>
            </tr>@empty<tr><td colspan="8" class="empty-state">Belum ada master perusahaan.</td></tr>@endforelse
        </tbody></x-data-table><div class="mt-3">{{ $companies->links() }}</div>
    </x-card>
</x-app-layout>
