<x-app-layout>
    <x-slot:title>Input Data Lembur</x-slot:title>
    <x-page-header title="Input Data Lembur" subtitle="PIC HC mencatat lembur untuk karyawan yang dipilih."/>

    <form method="post" action="{{ route('overtime.store') }}">
        @csrf
        <x-card>
            <div class="row g-3">
                <x-form-select name="employee_id" label="Karyawan" :options="$employees" required class="col-md-6"/>
                <x-form-input name="date" label="Tanggal" type="date" required class="col-md-6"/>
                <x-form-input name="start_time" label="Jam Mulai" type="time" required class="col-md-4"/>
                <x-form-input name="end_time" label="Jam Selesai" type="time" required class="col-md-4"/>
                <div class="col-12">
                    <label class="form-label">Keterangan / Pekerjaan</label>
                    <textarea class="form-control" name="reason" required>{{ old('reason') }}</textarea>
                </div>
            </div>
        </x-card>
        <div class="text-end">
            <a href="{{ route('overtime.index') }}" class="btn btn-outline-secondary">Batal</a>
            <button class="btn btn-primary">Simpan Data Lembur</button>
        </div>
    </form>
</x-app-layout>
