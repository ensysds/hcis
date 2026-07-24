<x-app-layout>
    <x-slot:title>Organization Management</x-slot:title>
    <x-page-header title="Organization Management" subtitle="Struktur organisasi bertingkat seperti tree SAP: company, unit organisasi, lalu posisi per baris.">
        <x-slot:actions>
            @if($company)
                @can('organization.create')
                    <a href="{{ route('organization.create', ['company_id' => $company->id]) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Unit Root Baru</a>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Perusahaan</label>
                <select name="company_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($companies as $item)
                        <option value="{{ $item->id }}" @selected($company?->id === $item->id)>{{ $item->code }} - {{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-7 text-secondary small">Pilih perusahaan untuk melihat struktur organisasi dalam bentuk hierarchy tree.</div>
        </form>
    </x-card>

    @if(!$company)
        <div class="alert alert-info">Buat perusahaan terlebih dahulu sebelum menyusun struktur organisasi.</div>
    @else
        <x-card title="Organization Tree {{ $company->name }}">
            <div class="sap-tree-shell">
                <div class="sap-tree-header">
                    <div>Objek</div>
                    <div>Kode</div>
                    <div>Tipe</div>
                    <div>Terisi</div>
                    <div>Status</div>
                    <div class="text-end">Aksi</div>
                </div>

                <ul class="sap-tree sap-tree-root">
                    <li class="sap-tree-item">
                        <div class="sap-tree-row sap-tree-company">
                            <div class="sap-tree-object">
                                <span class="sap-tree-toggle"></span>
                                <span class="sap-tree-icon"><i class="bi bi-buildings"></i></span>
                                <span class="sap-tree-level">-</span>
                                <span class="sap-tree-name">{{ $company->name }}</span>
                            </div>
                            <div class="sap-tree-code">{{ $company->code }}</div>
                            <div>Company</div>
                            <div>-</div>
                            <div><span class="badge text-bg-success">Aktif</span></div>
                            <div class="text-end">
                                @can('organization.create')
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('organization.create', ['company_id' => $company->id]) }}"><i class="bi bi-plus"></i> Root Unit</a>
                                @endcan
                            </div>
                        </div>

                        @if(count($tree))
                            <ul class="sap-tree">
                                @foreach($tree as $node)
                                    @include('organization._tree-node', ['node' => $node, 'company' => $company])
                                @endforeach
                            </ul>
                        @endif
                    </li>
                </ul>

                @if(!count($tree))
                    <div class="empty-state">Struktur belum dibuat. Mulai dengan unit root, lalu tambahkan sub-unit dan posisi.</div>
                @endif
            </div>
        </x-card>
    @endif
</x-app-layout>
