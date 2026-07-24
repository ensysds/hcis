<x-app-layout>
    <x-slot:title>Input Data Cuti</x-slot:title>
    <x-page-header title="Input Data Cuti" subtitle="PIC HC mencatat data cuti untuk karyawan yang dipilih."/>

    <form method="post" action="{{ route('leave.store') }}" enctype="multipart/form-data">
        @csrf
        <x-card title="Detail Cuti">
            <div class="row g-3">
                <x-form-select name="employee_id" label="Karyawan" :options="$employees" required class="col-md-6"/>
                <x-form-select name="leave_type_id" label="Jenis Cuti" :options="$types->pluck('name','id')" required class="col-md-6"/>
                <x-form-input name="start_date" label="Tanggal Mulai" type="date" required class="col-md-4"/>
                <x-form-input name="end_date" label="Tanggal Selesai" type="date" required class="col-md-4"/>
                <x-form-input name="attachment" label="Lampiran (maks. 2 MB)" type="file" class="col-md-4"/>
                <div class="col-12">
                    <label class="form-label">Keterangan</label>
                    <textarea name="reason" class="form-control" rows="3" required>{{ old('reason') }}</textarea>
                </div>
            </div>
        </x-card>
        <div class="text-end">
            <a href="{{ route('leave.index') }}" class="btn btn-outline-secondary">Batal</a>
            <button class="btn btn-primary">Simpan Data Cuti</button>
        </div>
    </form>
</x-app-layout>
