<x-app-layout>
    <x-slot:title>Role Management</x-slot:title>
    <x-page-header title="Role Management" subtitle="Kelola role dinamis dan akses lintas modul.">
        <x-slot:actions>
            @can('role.create')
                <form method="post" action="{{ route('roles.sync-defaults') }}">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat"></i> Sync Role Modul</button>
                </form>
                <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Role Baru</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form class="row g-2 mb-3">
            <div class="col-md-5"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Cari nama role"></div>
            <div class="col-md-3"><button class="btn btn-outline-primary btn-sm">Terapkan Filter</button></div>
        </form>

        <x-data-table>
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Jenis</th>
                    <th>Permission</th>
                    <th>User</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                    <tr>
                        <td class="fw-semibold">{{ $role->name }}</td>
                        <td>
                            @if($role->name === 'super_admin')
                                <span class="badge bg-primary">System</span>
                            @elseif(str_starts_with($role->name, 'module_'))
                                <span class="badge bg-info text-dark">Default Modul</span>
                            @else
                                <span class="badge bg-secondary">Custom</span>
                            @endif
                        </td>
                        <td>{{ $role->permissions_count }}</td>
                        <td>{{ $userCounts[$role->id] ?? 0 }}</td>
                        <td class="text-end">
                            @can('role.update')
                                <a class="btn btn-sm btn-link" href="{{ route('roles.edit', $role) }}"><i class="bi bi-pencil"></i></a>
                            @endcan
                            @can('role.delete')
                                @if($role->name !== 'super_admin' && (($userCounts[$role->id] ?? 0) === 0))
                                    <form class="d-inline" method="post" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Hapus role ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state">Belum ada role.</td></tr>
                @endforelse
            </tbody>
        </x-data-table>
        <div class="mt-3">{{ $roles->links() }}</div>
    </x-card>
</x-app-layout>
