<x-app-layout>
    <x-slot:title>Administrasi Kehadiran</x-slot:title>
    <x-page-header title="Administrasi Kehadiran" subtitle="Monitoring data kehadiran karyawan untuk PIC HC."/>

    <x-card>
        <x-data-table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Karyawan</th>
                    <th>Masuk</th>
                    <th>Pulang</th>
                    <th>Terlambat</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attendances as $a)
                    <tr>
                        <td>{{ $a->date->format('d M Y') }}</td>
                        <td>{{ $a->employee->full_name }}</td>
                        <td>{{ $a->check_in_at?->format('H:i') ?? '-' }}</td>
                        <td>{{ $a->check_out_at?->format('H:i') ?? '-' }}</td>
                        <td>{{ $a->late_minutes }} menit</td>
                        <td><x-status-badge :status="$a->status"/></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">Belum ada data kehadiran.</td></tr>
                @endforelse
            </tbody>
        </x-data-table>
        <div class="mt-3">{{ $attendances->links() }}</div>
    </x-card>
</x-app-layout>
