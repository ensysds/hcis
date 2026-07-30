<x-app-layout>
    <x-slot:title>Dashboard HR</x-slot:title>
    <x-page-header title="Dashboard Human Capital" subtitle="Pantau tenaga kerja dan aktivitas utama organisasi dalam satu tampilan."/>
    <section class="hcis-card dashboard-welcome mb-3">
        <div class="card-body">
            <div>
                <div class="eyebrow">Workspace Overview</div>
                <h2>Selamat datang, {{ str(auth()->user()->name)->before(' ') }}.</h2>
                <p>{{ now()->translatedFormat('l, d F Y') }} &middot; Semua informasi penting HC tersedia di sini.</p>
            </div>
            @can('employee.create')
                <a class="btn btn-light" href="{{ route('employees.create') }}"><i class="bi bi-person-plus me-1"></i> Tambah Karyawan</a>
            @endcan
        </div>
    </section>
    <div class="dashboard-stats">
        <x-stat-tile label="Headcount Aktif" :value="$headcount" icon="people" hint="Karyawan aktif saat ini"/>
        <x-stat-tile label="Hadir Hari Ini" :value="$present" icon="person-check" tone="success" hint="Kehadiran tercatat"/>
        <x-stat-tile label="Menunggu Approval" :value="$pending" icon="hourglass-split" tone="warning" hint="Perlu tindak lanjut"/>
        <x-stat-tile label="Payroll Period" :value="$periods->count()" icon="wallet2" tone="info" hint="Periode payroll terbaru"/>
    </div>
    <div class="dashboard-main-grid">
        <x-card title="Distribusi Karyawan"><div class="chart-wrap"><canvas id="headcountChart"></canvas></div></x-card>
        <div>
            <x-card title="Payroll Terbaru">
                <div class="payroll-list">
                    @forelse($periods as $period)
                        <div class="payroll-item">
                            <span class="payroll-item-icon"><i class="bi bi-receipt"></i></span>
                            <span class="payroll-item-copy"><strong>{{ $period->name }}</strong><small>Periode penggajian</small></span>
                            <x-status-badge :status="$period->status"/>
                        </div>
                    @empty
                        <div class="empty-state py-4">Belum ada periode payroll.</div>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
    <x-slot:scripts>
        <script>
            const renderHeadcountChart = () => {
                const canvas = document.getElementById('headcountChart');

                if (!canvas || window.Chart.getChart(canvas)) {
                    return;
                }

                new window.Chart(canvas, {
                    type: 'bar',
                    data: {labels: @json($departments->keys()), datasets: [{label: 'Karyawan', data: @json($departments->values()), backgroundColor: '#3b82f6', hoverBackgroundColor: '#2563eb', borderRadius: 7, borderSkipped: false, barThickness: 30}]},
                    options: {maintainAspectRatio: false, plugins: {legend: {display: false}, tooltip: {backgroundColor: '#172033', padding: 11, cornerRadius: 8, displayColors: false}}, scales: {x: {grid: {display: false}, border: {display: false}, ticks: {color: '#7a879a', font: {size: 10}}}, y: {beginAtZero: true, border: {display: false}, grid: {color: '#edf0f4'}, ticks: {precision: 0, color: '#7a879a', font: {size: 10}}}}}
                });
            };

            if (window.Chart) {
                renderHeadcountChart();
            } else {
                window.addEventListener('hcis:ready', renderHeadcountChart, {once: true});
            }
        </script>
    </x-slot:scripts>
</x-app-layout>
