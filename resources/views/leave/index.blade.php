<x-app-layout>
    <x-slot:title>Administrasi Cuti</x-slot:title>
    <x-page-header title="Administrasi Cuti" subtitle="Pencatatan dan monitoring data cuti karyawan oleh PIC HC.">
        <x-slot:actions>
            @can('leave.create')
                <a href="{{ route('leave.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Input Data Cuti</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card title="Daftar Cuti Karyawan">
        <x-data-table>
            <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Jenis</th>
                    <th>Periode</th>
                    <th>Durasi</th>
                    <th>Alasan</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $item)
                    <tr>
                        <td>{{ $item->employee->full_name }}</td>
                        <td>{{ $item->leaveType->name }}</td>
                        <td>{{ $item->start_date->format('d M') }} - {{ $item->end_date->format('d M Y') }}</td>
                        <td>{{ $item->days }} hari</td>
                        <td>{{ Str::limit($item->reason, 45) }} @if($item->attachment_path)<a href="{{ route('leave.attachment',$item) }}" title="Lampiran"><i class="bi bi-paperclip"></i></a>@endif</td>
                        <td><x-status-badge :status="$item->status"/></td>
                        <td>
                            @can('leave.update')
                                @if(in_array($item->status, ['draft', 'submitted'], true))
                                    <form method="post" action="{{ route('leave.cancel', $item) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-link text-danger">Batal</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">Belum ada data cuti.</td></tr>
                @endforelse
            </tbody>
        </x-data-table>
        <div class="mt-3">{{ $requests->links() }}</div>
    </x-card>
</x-app-layout>
