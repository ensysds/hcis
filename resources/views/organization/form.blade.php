<x-app-layout>
    <x-slot:title>{{ $unit->exists ? 'Edit Unit Organisasi' : 'Unit Organisasi Baru' }}</x-slot:title>
    <x-page-header :title="$unit->exists ? 'Edit Unit Organisasi' : 'Unit Organisasi Baru'" subtitle="Bangun hierarki organisasi dengan relasi induk dan sub-unit."/>
    <form method="post" action="{{ $unit->exists ? route('organization.update', $unit) : route('organization.store') }}">@csrf @if($unit->exists) @method('PUT') @endif
        <x-card title="Unit Organisasi"><div class="row g-3">
            <x-form-select name="company_id" label="Perusahaan" :options="$companies->pluck('name','id')" :value="$unit->company_id" required class="col-md-6"/>
            <x-form-select name="parent_id" label="Unit Induk" :options="$parents->mapWithKeys(fn($p) => [$p->id => $p->code.' · '.$p->name])" :value="$unit->parent_id" placeholder="Root / tanpa induk" class="col-md-6"/>
            <x-form-input name="code" label="Kode Unit" :value="$unit->code" required class="col-md-3"/>
            <x-form-input name="name" label="Nama Unit" :value="$unit->name" required class="col-md-5"/>
            <x-form-select name="type" label="Tipe Unit" :options="['group'=>'Group','directorate'=>'Direktorat','division'=>'Divisi','department'=>'Departemen','section'=>'Seksi','team'=>'Tim']" :value="$unit->type ?: 'department'" required class="col-md-2"/>
            <x-form-select name="is_active" label="Status" :options="[1=>'Aktif',0=>'Nonaktif']" :value="$unit->exists ? (int)$unit->is_active : 1" required class="col-md-2"/>
            <div class="col-12"><label class="form-label">Deskripsi / Fungsi</label><textarea name="description" rows="3" class="form-control form-control-sm">{{ old('description', $unit->description) }}</textarea></div>
        </div></x-card>
        <div class="text-end"><a href="{{ route('organization.index', ['company_id' => $unit->company_id]) }}" class="btn btn-outline-secondary">Batal</a> <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Simpan Unit</button></div>
    </form>
    <x-slot:scripts><script>document.getElementById('company_id')?.addEventListener('change',function(){if(this.value!=='{{ $unit->company_id }}')document.getElementById('parent_id').value='';});</script></x-slot:scripts>
</x-app-layout>
