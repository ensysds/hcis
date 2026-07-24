<x-app-layout>
    <x-slot:title>Administrasi Lembur</x-slot:title>
    <x-page-header title="Administrasi Lembur" subtitle="Pencatatan dan monitoring jam lembur karyawan oleh PIC HC.">
        <x-slot:actions>
            @can('overtime.create')
                <a href="{{ route('overtime.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Input Data Lembur</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <x-data-table>
            <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Tanggal</th>
                    <th>Waktu</th>
                    <th>Jam</th>
                    <th>Alasan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $item)
                    <tr>
                        <td>{{ $item->employee->full_name }}</td>
                        <td>{{ $item->date->format('d M Y') }}</td>
                        <td>{{ substr($item->start_time, 0, 5) }} - {{ substr($item->end_time, 0, 5) }}</td>
                        <td>{{ $item->hours }}</td>
                        <td>{{ $item->reason }}</td>
                        <td><x-status-badge :status="$item->status"/></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">Belum ada data lembur.</td></tr>
                @endforelse
            </tbody>
        </x-data-table>
        <div class="mt-3">{{ $requests->links() }}</div>
    </x-card>
</x-app-layout>
