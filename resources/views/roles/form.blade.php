<x-app-layout>
    <x-slot:title>{{ $role->exists ? 'Edit Role' : 'Role Baru' }}</x-slot:title>
    <x-page-header :title="$role->exists ? 'Edit Role' : 'Role Baru'" subtitle="Atur CRUD dan akses tambahan per modul.">
        <x-slot:actions>
            <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
        </x-slot:actions>
    </x-page-header>

    <form method="post" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}">
        @csrf
        @if($role->exists)@method('PUT')@endif

        <x-card title="Identitas Role">
            <div class="row g-3 align-items-end">
                <x-form-input name="name" label="Nama Role" :value="$role->name" required class="col-md-5" :readonly="$role->name === 'super_admin'"/>
                <div class="col-md-7 small text-secondary">
                    Role default modul memakai prefix <code>module_</code>. User bisa punya beberapa role sekaligus; permission akan digabung otomatis.
                </div>
            </div>
        </x-card>

        <x-card title="Permission Matrix">
            @if($role->name === 'super_admin')
                <div class="alert alert-info py-2">Role <strong>super_admin</strong> selalu mendapat semua permission dan tidak bisa dikurangi dari layar ini.</div>
            @endif

            <div class="d-flex gap-2 mb-3">
                <button type="button" class="btn btn-outline-primary btn-sm" data-check-crud {{ $role->name === 'super_admin' ? 'disabled' : '' }}><i class="bi bi-check2-square"></i> Pilih CRUD</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-clear-permissions {{ $role->name === 'super_admin' ? 'disabled' : '' }}><i class="bi bi-eraser"></i> Kosongkan</button>
            </div>

            <x-data-table>
                <thead>
                    <tr>
                        <th>Modul</th>
                        @foreach($actions as $actionLabel)
                            <th class="text-center">{{ $actionLabel }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($modules as $moduleKey => $module)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $module['label'] }}</div>
                                <div class="text-secondary">{{ $module['group'] }} &middot; {{ $moduleKey }}</div>
                            </td>
                            @foreach($actions as $actionKey => $actionLabel)
                                @php($permission = "{$moduleKey}.{$actionKey}")
                                <td class="text-center">
                                    <input
                                        class="form-check-input permission-check"
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $permission }}"
                                        data-action="{{ $actionKey }}"
                                        @checked($role->name === 'super_admin' || in_array($permission, $selectedPermissions, true))
                                        @disabled($role->name === 'super_admin')
                                    >
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </x-data-table>
        </x-card>

        <div class="text-end">
            <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary">Batal</a>
            <button class="btn btn-primary">Simpan Role</button>
        </div>
    </form>

    <x-slot:scripts>
        <script>
            document.querySelector('[data-check-crud]')?.addEventListener('click', () => {
                document.querySelectorAll('.permission-check').forEach((item) => {
                    item.checked = ['view', 'create', 'update', 'delete'].includes(item.dataset.action);
                });
            });
            document.querySelector('[data-clear-permissions]')?.addEventListener('click', () => {
                document.querySelectorAll('.permission-check').forEach((item) => item.checked = false);
            });
        </script>
    </x-slot:scripts>
</x-app-layout>
