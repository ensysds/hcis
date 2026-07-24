<x-app-layout>
    <x-slot:title>Payroll</x-slot:title>
    <x-page-header title="Payroll Run" subtitle="Buat periode, hitung komponen, finalisasi, dan terbitkan slip.">
        <x-slot:actions>
            @can('payroll.create')
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newPeriod"><i class="bi bi-plus"></i> Periode Baru</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <x-data-table>
            <thead>
                <tr>
                    <th>Periode</th>
                    <th>Perusahaan</th>
                    <th>Rentang</th>
                    <th>Tanggal Bayar</th>
                    <th>Slip</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($periods as $p)
                    <tr>
                        <td class="fw-semibold">{{ $p->name }}</td>
                        <td>{{ $p->company?->name ?? '-' }}</td>
                        <td>{{ $p->start_date->format('d M') }} - {{ $p->end_date->format('d M Y') }}</td>
                        <td>{{ $p->pay_date->format('d M Y') }}</td>
                        <td>{{ $p->payslips_count }}</td>
                        <td><x-status-badge :status="$p->status"/></td>
                        <td class="text-end">
                            @can('payroll.update')
                                @if($p->status !== 'finalized')
                                    <form method="post" action="{{ route('payroll.run', $p) }}" onsubmit="return confirm('Jalankan dan finalisasi payroll?')">
                                        @csrf
                                        <button class="btn btn-primary btn-sm">Jalankan Payroll</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-data-table>
        <div class="mt-3">{{ $periods->links() }}</div>
    </x-card>

    @can('payroll.create')
        <x-modal id="newPeriod" title="Periode Payroll Baru">
            <form method="post" action="{{ route('payroll.store') }}">
                @csrf
                <x-form-select name="company_id" label="Perusahaan" :options="$companies->pluck('name','id')" required class="mb-3"/>
                <x-form-input name="name" label="Nama Periode" required class="mb-3"/>
                <x-form-input name="start_date" label="Tanggal Mulai" type="date" required class="mb-3"/>
                <x-form-input name="end_date" label="Tanggal Selesai" type="date" required class="mb-3"/>
                <x-form-input name="pay_date" label="Tanggal Bayar" type="date" required class="mb-3"/>
                <div class="text-end"><button class="btn btn-primary">Simpan</button></div>
            </form>
        </x-modal>
    @endcan
</x-app-layout>
