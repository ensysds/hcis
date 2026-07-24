<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\CompanyAccess;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::withCount(['departments', 'employees'])->whereIn('id', CompanyAccess::ids($request->user()))
            ->when($request->q, fn ($query, $term) => $query->where(fn ($filter) => $filter
                ->where('name', 'like', "%{$term}%")
                ->orWhere('legal_name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('tax_number', 'like', "%{$term}%")))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('companies.index', compact('companies'));
    }

    public function create()
    {
        return view('companies.form', ['company' => new Company]);
    }

    public function store(Request $request)
    {
        $company = Company::create($this->validated($request));
        if (! $request->user()->hasRole('super_admin')) {
            $request->user()->companies()->syncWithoutDetaching([$company->id => ['access_level' => 'company']]);
        }

        return redirect()->route('companies.index')->with('success', 'Perusahaan berhasil ditambahkan.');
    }

    public function edit(Company $company)
    {
        CompanyAccess::authorize(request()->user(), $company->id);
        return view('companies.form', compact('company'));
    }

    public function update(Request $request, Company $company)
    {
        CompanyAccess::authorize($request->user(), $company->id);
        $company->update($this->validated($request, $company));

        return redirect()->route('companies.index')->with('success', 'Data perusahaan berhasil diperbarui.');
    }

    public function destroy(Company $company)
    {
        CompanyAccess::authorize(request()->user(), $company->id);
        if ($company->employees()->exists() || $company->departments()->exists()) {
            return back()->withErrors(['company' => 'Perusahaan masih memiliki struktur organisasi atau karyawan dan tidak dapat diarsipkan.']);
        }

        $company->delete();

        return redirect()->route('companies.index')->with('success', 'Perusahaan berhasil diarsipkan.');
    }

    private function validated(Request $request, ?Company $company = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('companies', 'code')->ignore($company)],
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'tax_office' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'url', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
