@php($unit = $node['unit'])
<li class="sap-tree-item">
    <div class="sap-tree-row">
        <div class="sap-tree-object">
            <span class="sap-tree-toggle">@if(count($node['children']) || $unit->positions->isNotEmpty())<i class="bi bi-caret-down-fill"></i>@endif</span>
            <span class="sap-tree-icon"><i class="bi bi-diagram-3"></i></span>
            <span class="sap-tree-level">{{ str_repeat('-', $node['depth']) }}</span>
            <span>
                <span class="sap-tree-name">{{ $unit->name }}</span>
                @if($unit->description)
                    <span class="sap-tree-description">{{ Str::limit($unit->description, 60) }}</span>
                @endif
            </span>
        </div>
        <div class="sap-tree-code">{{ $unit->code }}</div>
        <div>{{ ucfirst($unit->type) }}</div>
        <div>{{ $unit->employees->count() }}</div>
        <div><span class="badge {{ $unit->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $unit->is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
        <div class="text-end sap-tree-actions">
            @can('organization.create')
                <a class="btn btn-sm btn-link" href="{{ route('organization.create', ['company_id' => $company->id, 'parent_id' => $unit->id]) }}" title="Tambah sub-unit"><i class="bi bi-plus-square"></i></a>
                <button type="button" class="btn btn-sm btn-link" data-bs-toggle="collapse" data-bs-target="#position-{{ $unit->id }}" title="Tambah posisi"><i class="bi bi-person-plus"></i></button>
            @endcan
            @can('organization.update')
                <a class="btn btn-sm btn-link" href="{{ route('organization.edit', $unit) }}" title="Edit unit"><i class="bi bi-pencil"></i></a>
            @endcan
            @can('organization.delete')
                <form class="d-inline" method="post" action="{{ route('organization.destroy', $unit) }}" onsubmit="return confirm('Arsipkan unit ini?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-link text-danger" title="Arsipkan unit"><i class="bi bi-archive"></i></button>
                </form>
            @endcan
        </div>
    </div>

    @can('organization.create')
        <div class="collapse sap-tree-position-form" id="position-{{ $unit->id }}">
            <form method="post" action="{{ route('organization.positions.store') }}" class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="department_id" value="{{ $unit->id }}">
                <div class="col-md-2">
                    <label class="form-label">Kode Posisi</label>
                    <input name="code" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Nama Posisi</label>
                    <input name="name" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Headcount</label>
                    <input name="headcount" type="number" min="1" value="1" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary btn-sm">Tambah Posisi</button>
                </div>
            </form>
        </div>
    @endcan

    @if($unit->positions->isNotEmpty() || count($node['children']))
        <ul class="sap-tree">
            @foreach($unit->positions as $position)
                <li class="sap-tree-item sap-tree-position-item">
                    <div class="sap-tree-row sap-tree-position-row">
                        <div class="sap-tree-object">
                            <span class="sap-tree-toggle"></span>
                            <span class="sap-tree-icon sap-tree-position-icon"><i class="bi bi-person-vcard"></i></span>
                            <span class="sap-tree-level">{{ str_repeat('-', $node['depth'] + 1) }}</span>
                            <span class="sap-tree-name">{{ $position->name }}</span>
                        </div>
                        <div class="sap-tree-code">{{ $position->code }}</div>
                        <div>Position</div>
                        <div>{{ $position->employees->count() }}/{{ $position->headcount }}</div>
                        <div><span class="badge text-bg-light border">Planned</span></div>
                        <div class="text-end sap-tree-actions">
                            @can('organization.delete')
                                <form class="d-inline" method="post" action="{{ route('organization.positions.destroy', $position) }}" onsubmit="return confirm('Arsipkan posisi ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger" title="Arsipkan posisi"><i class="bi bi-archive"></i></button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </li>
            @endforeach

            @foreach($node['children'] as $child)
                @include('organization._tree-node', ['node' => $child, 'company' => $company])
            @endforeach
        </ul>
    @endif
</li>
