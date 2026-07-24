<x-app-layout>
    <x-slot:title>User Management</x-slot:title>
    <x-page-header title="User Management" subtitle="Kelola akun login dan assignment multi-role.">
        <x-slot:actions>
            @can('user.create')
                <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> User Baru</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form class="row g-2 mb-3">
            <div class="col-md-4"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Cari nama, email, atau NRP"></div>
            <div class="col-md-4">
                <select class="form-select form-select-sm" name="role">
                    <option value="">Semua role</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-outline-primary btn-sm">Terapkan Filter</button></div>
        </form>

        <x-data-table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Akses PT</th>
                    <th>Status</th>
                    <th>Role</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $user->name }}</div>
                            <div class="text-secondary">{{ $user->email }} &middot; {{ $user->nrp }}</div>
                        </td>
                        <td>
                            @if($user->hasRole('super_admin'))
                                <span class="badge bg-dark">Semua PT</span>
                            @else
                            @forelse($user->companies as $company)
                                <span class="badge bg-light text-dark border mb-1">{{ $company->code }}</span>
                            @empty
                                <span class="text-secondary">-</span>
                            @endforelse
                            @endif
                        </td>
                        <td><x-status-badge :status="$user->is_active ? 'active' : 'inactive'"/></td>
                        <td>
                            @forelse($user->roles as $role)
                                <span class="badge bg-light text-dark border mb-1">{{ $role->name }}</span>
                            @empty
                                <span class="text-secondary">Belum ada role</span>
                            @endforelse
                        </td>
                        <td class="text-end">
                            @can('user.update')
                                <a class="btn btn-sm btn-link" href="{{ route('users.edit', $user) }}"><i class="bi bi-pencil"></i></a>
                            @endcan
                            @can('user.delete')
                                @if(!auth()->user()->is($user))
                                    <form class="d-inline" method="post" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Hapus user ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state">User tidak ditemukan.</td></tr>
                @endforelse
            </tbody>
        </x-data-table>
        <div class="mt-3">{{ $users->links() }}</div>
    </x-card>
</x-app-layout>
