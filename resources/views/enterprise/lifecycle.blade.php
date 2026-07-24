<x-app-layout>
<x-slot:title>Employee Lifecycle</x-slot:title>
<x-page-header title="Employee Lifecycle" subtitle="Kontrak, mutasi, promosi, perubahan status, dan terminasi dengan histori efektif."/>
<div class="row g-3 mb-3">
    <div class="col-xl-6"><x-card title="Kontrak Kerja Baru"><form method="post" action="{{ route('enterprise.contracts.store') }}" class="row g-2">@csrf
        @include('enterprise._company-employee')
        <x-form-input name="contract_number" label="Nomor Kontrak" class="col-md-4" required/>
        <x-form-select name="contract_type" label="Jenis" :options="['permanent'=>'Tetap','fixed_term'=>'PKWT','probation'=>'Probation','internship'=>'Magang','consultant'=>'Konsultan']" class="col-md-4" required/>
        <x-form-input name="start_date" label="Mulai" type="date" class="col-md-4" required/><x-form-input name="end_date" label="Berakhir" type="date" class="col-md-4"/><x-form-input name="probation_end_date" label="Akhir Probation" type="date" class="col-md-4"/>
        <div class="col-12"><label class="form-label">Catatan</label><textarea name="notes" class="form-control form-control-sm"></textarea></div><div class="col-12 text-end"><button class="btn btn-primary btn-sm">Simpan Draft Kontrak</button></div>
    </form></x-card></div>
    <div class="col-xl-6"><x-card title="Tindakan Kepegawaian"><form method="post" action="{{ route('enterprise.actions.store') }}" class="row g-2">@csrf
        @include('enterprise._company-employee')
        <x-form-select name="action_type" label="Jenis Tindakan" :options="['hire'=>'Hire','transfer'=>'Mutasi','promotion'=>'Promosi','demotion'=>'Demosi','secondment'=>'Penugasan','contract_change'=>'Perubahan Kontrak','status_change'=>'Perubahan Status','termination'=>'Terminasi','retirement'=>'Pensiun']" class="col-md-4" required/>
        <x-form-input name="effective_date" label="Tanggal Efektif" type="date" class="col-md-4" required/>
        <x-form-select name="to_company_id" label="Perusahaan Tujuan" :options="$companies->pluck('name','id')" class="col-md-4"/>
        <x-form-select name="to_department_id" label="Unit Tujuan" :options="\App\Models\Department::whereIn('company_id',$companies->pluck('id'))->pluck('name','id')" class="col-md-4"/>
        <x-form-select name="to_position_id" label="Posisi Tujuan" :options="\App\Models\Position::whereHas('department',fn($q)=>$q->whereIn('company_id',$companies->pluck('id')))->pluck('name','id')" class="col-md-4"/>
        <x-form-select name="to_job_grade_id" label="Grade Tujuan" :options="\Illuminate\Support\Facades\DB::table('job_grades')->pluck('name','id')" class="col-md-4"/>
        <div class="col-12"><label class="form-label">Alasan / Dasar Keputusan</label><textarea name="reason" class="form-control form-control-sm" required></textarea></div><div class="col-12 text-end"><button class="btn btn-primary btn-sm">Simpan Tindakan</button></div>
    </form></x-card></div>
</div>
<x-card title="Riwayat Kontrak" class="mb-3"><x-data-table><thead><tr><th>Kontrak</th><th>Karyawan</th><th>Perusahaan</th><th>Periode</th><th>Status</th><th></th></tr></thead><tbody>@forelse($contracts as $item)<tr><td>{{ $item->contract_number }}<div class="text-secondary">{{ strtoupper($item->contract_type) }}</div></td><td>{{ $item->employee->full_name }}</td><td>{{ $item->company->name }}</td><td>{{ $item->start_date->format('d M Y') }} – {{ $item->end_date?->format('d M Y') ?? 'Tidak terbatas' }}</td><td><x-status-badge :status="$item->status"/></td><td class="text-end">@if($item->status==='draft')<form method="post" action="{{ route('enterprise.contracts.approve',$item) }}">@csrf<button class="btn btn-success btn-sm">Aktifkan</button></form>@endif</td></tr>@empty<tr><td colspan="6" class="empty-state">Belum ada kontrak.</td></tr>@endforelse</tbody></x-data-table>{{ $contracts->links() }}</x-card>
<x-card title="Riwayat Tindakan Kepegawaian"><x-data-table><thead><tr><th>Referensi</th><th>Karyawan</th><th>Jenis</th><th>Efektif</th><th>Tujuan</th><th>Status</th><th></th></tr></thead><tbody>@forelse($actions as $item)<tr><td>{{ $item->reference_no }}</td><td>{{ $item->employee->full_name }}</td><td>{{ ucwords(str_replace('_',' ',$item->action_type)) }}</td><td>{{ $item->effective_date->format('d M Y') }}</td><td>{{ $item->toCompany?->name ?? '-' }}<div class="text-secondary">{{ $item->toDepartment?->name }} {{ $item->toPosition?->name }}</div></td><td><x-status-badge :status="$item->status"/></td><td class="text-end">@if($item->status==='draft')<form method="post" action="{{ route('enterprise.actions.approve',$item) }}">@csrf<button class="btn btn-success btn-sm">Setujui & Terapkan</button></form>@endif</td></tr>@empty<tr><td colspan="7" class="empty-state">Belum ada tindakan.</td></tr>@endforelse</tbody></x-data-table>{{ $actions->links() }}</x-card>
</x-app-layout>
