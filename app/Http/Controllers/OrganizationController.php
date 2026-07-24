<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Support\CompanyAccess;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::whereIn('id', CompanyAccess::ids($request->user()))->where('is_active', true)->orderBy('name')->get();
        $company = $request->company_id ? $companies->firstWhere('id', (int) $request->company_id) : $companies->first();
        $units = $company ? Department::with(['positions.employees', 'employees'])->where('company_id', $company->id)->orderBy('name')->get() : collect();

        return view('organization.index', [
            'companies' => $companies,
            'company' => $company,
            'tree' => $this->buildTree($units),
        ]);
    }

    public function create(Request $request)
    {
        $unit = new Department(['company_id' => $request->integer('company_id'), 'parent_id' => $request->integer('parent_id') ?: null]);

        return view('organization.form', $this->formData($unit));
    }

    public function store(Request $request)
    {
        Department::create($this->validated($request));

        return redirect()->route('organization.index', ['company_id' => $request->company_id])->with('success', 'Unit organisasi berhasil ditambahkan.');
    }

    public function edit(Department $unit)
    {
        CompanyAccess::authorize(request()->user(), $unit->company_id);
        return view('organization.form', $this->formData($unit));
    }

    public function update(Request $request, Department $unit)
    {
        CompanyAccess::authorize($request->user(), $unit->company_id);
        $payload = $this->validated($request, $unit);
        if ((int) $payload['company_id'] !== $unit->company_id && ($unit->children()->exists() || $unit->positions()->exists() || $unit->employees()->exists())) {
            throw ValidationException::withMessages(['company_id' => 'Unit yang sudah memiliki sub-unit, posisi, atau karyawan tidak dapat dipindahkan ke perusahaan lain.']);
        }
        if ($payload['parent_id'] && in_array((int) $payload['parent_id'], $this->descendantIds($unit), true)) {
            throw ValidationException::withMessages(['parent_id' => 'Unit induk tidak boleh berasal dari turunan unit ini.']);
        }
        $unit->update($payload);

        return redirect()->route('organization.index', ['company_id' => $unit->company_id])->with('success', 'Unit organisasi berhasil diperbarui.');
    }

    public function destroy(Department $unit)
    {
        CompanyAccess::authorize(request()->user(), $unit->company_id);
        if ($unit->children()->exists() || $unit->employees()->exists() || $unit->positions()->exists()) {
            return back()->withErrors(['unit' => 'Unit masih memiliki sub-unit, posisi, atau karyawan dan tidak dapat diarsipkan.']);
        }
        $companyId = $unit->company_id;
        $unit->delete();

        return redirect()->route('organization.index', ['company_id' => $companyId])->with('success', 'Unit organisasi berhasil diarsipkan.');
    }

    public function storePosition(Request $request)
    {
        $payload = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'code' => ['required', 'string', 'max:30', 'unique:positions,code'],
            'name' => ['required', 'string', 'max:150'],
            'headcount' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
        $unit = Department::findOrFail($payload['department_id']);
        CompanyAccess::authorize($request->user(), $unit->company_id);
        Position::create($payload);

        return redirect()->route('organization.index', ['company_id' => $unit->company_id])->with('success', 'Posisi berhasil ditambahkan ke struktur.');
    }

    public function destroyPosition(Position $position)
    {
        CompanyAccess::authorize(request()->user(), $position->department->company_id);
        if ($position->employees()->exists()) {
            return back()->withErrors(['position' => 'Posisi masih ditempati karyawan dan tidak dapat diarsipkan.']);
        }
        $companyId = $position->department->company_id;
        $position->delete();

        return redirect()->route('organization.index', ['company_id' => $companyId])->with('success', 'Posisi berhasil diarsipkan.');
    }

    private function validated(Request $request, ?Department $unit = null): array
    {
        $payload = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'parent_id' => ['nullable', 'exists:departments,id', Rule::notIn(array_filter([$unit?->id]))],
            'code' => ['required', 'string', 'max:30', Rule::unique('departments', 'code')->ignore($unit)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['group', 'directorate', 'division', 'department', 'section', 'team'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);
        $payload['parent_id'] = $payload['parent_id'] ?? null;
        CompanyAccess::authorize($request->user(), $payload['company_id']);
        if ($payload['parent_id'] && ! Department::whereKey($payload['parent_id'])->where('company_id', $payload['company_id'])->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'Unit induk harus berasal dari perusahaan yang sama.']);
        }

        return $payload;
    }

    private function formData(Department $unit): array
    {
        $companies = Company::whereIn('id', CompanyAccess::ids(request()->user()))->where('is_active', true)->orderBy('name')->get();
        $parents = Department::when($unit->company_id, fn ($query) => $query->where('company_id', $unit->company_id))
            ->when($unit->exists, fn ($query) => $query->whereKeyNot($unit->id))->orderBy('name')->get();

        return compact('unit', 'companies', 'parents');
    }

    private function buildTree($units, ?int $parentId = null, int $depth = 1): array
    {
        return $units
            ->where('parent_id', $parentId)
            ->map(fn (Department $unit) => [
                'unit' => $unit,
                'depth' => $depth,
                'children' => $this->buildTree($units, $unit->id, $depth + 1),
            ])
            ->values()
            ->all();
    }

    private function descendantIds(Department $unit): array
    {
        $ids = [];
        $pending = [$unit->id];
        while ($pending) {
            $children = Department::whereIn('parent_id', $pending)->pluck('id')->all();
            $ids = array_merge($ids, $children);
            $pending = $children;
        }

        return $ids;
    }
}
