<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\CalendarHoliday;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAction;
use App\Models\EmploymentContract;
use App\Models\HcCase;
use App\Models\LearningParticipant;
use App\Models\LearningProgram;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\Position;
use App\Models\SuccessionPlan;
use App\Models\User;
use App\Models\WorkCalendar;
use App\Support\CompanyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EnterpriseHcController extends Controller
{
    public function lifecycle(Request $request)
    {
        $this->allow($request, 'lifecycle.view');
        $ids = CompanyAccess::ids($request->user());

        return view('enterprise.lifecycle', $this->lookups($request) + [
            'contracts' => EmploymentContract::with(['company', 'employee'])->whereIn('company_id', $ids)->latest()->paginate(15, ['*'], 'contracts'),
            'actions' => EmployeeAction::with(['company', 'employee', 'toCompany', 'toDepartment', 'toPosition'])->whereIn('company_id', $ids)->latest('effective_date')->paginate(15, ['*'], 'actions'),
        ]);
    }

    public function storeContract(Request $request)
    {
        $this->allow($request, 'lifecycle.create');
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'], 'employee_id' => ['required', 'exists:employees,id'],
            'contract_number' => ['required', 'string', 'max:80', 'unique:employment_contracts,contract_number'],
            'contract_type' => ['required', Rule::in(['permanent', 'fixed_term', 'probation', 'internship', 'consultant'])],
            'start_date' => ['required', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:start_date'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->employeeInCompany($request, $data['employee_id'], $data['company_id']);
        EmploymentContract::create($data + ['status' => 'draft', 'created_by' => $request->user()->id]);

        return back()->with('success', 'Kontrak kerja berhasil dicatat sebagai draft.');
    }

    public function approveContract(Request $request, EmploymentContract $contract)
    {
        $this->allow($request, 'lifecycle.approve');
        CompanyAccess::authorize($request->user(), $contract->company_id);
        abort_unless($contract->status === 'draft', 422, 'Hanya kontrak draft yang dapat diaktifkan.');
        DB::transaction(function () use ($contract, $request) {
            EmploymentContract::where('employee_id', $contract->employee_id)->where('status', 'active')->update(['status' => 'superseded']);
            $contract->update(['status' => 'active', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $contract->employee()->update([
                'employment_status' => $contract->contract_type,
                'contract_start_date' => $contract->start_date,
                'contract_end_date' => $contract->end_date,
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Kontrak diaktifkan dan data induk karyawan diperbarui.');
    }

    public function storeAction(Request $request)
    {
        $this->allow($request, 'lifecycle.create');
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'], 'employee_id' => ['required', 'exists:employees,id'],
            'action_type' => ['required', Rule::in(['hire', 'transfer', 'promotion', 'demotion', 'secondment', 'contract_change', 'status_change', 'termination', 'retirement'])],
            'effective_date' => ['required', 'date'], 'to_company_id' => ['nullable', 'exists:companies,id'],
            'to_department_id' => ['nullable', 'exists:departments,id'], 'to_position_id' => ['nullable', 'exists:positions,id'],
            'to_job_grade_id' => ['nullable', 'exists:job_grades,id'], 'reason' => ['required', 'string', 'max:2000'],
        ]);
        $employee = $this->employeeInCompany($request, $data['employee_id'], $data['company_id']);
        if (! empty($data['to_company_id'])) {
            CompanyAccess::authorize($request->user(), $data['to_company_id']);
        }
        EmployeeAction::create($data + [
            'reference_no' => 'ACT-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'from_company_id' => $employee->company_id, 'from_department_id' => $employee->department_id,
            'from_position_id' => $employee->position_id, 'from_job_grade_id' => $employee->job_grade_id,
            'status' => 'draft', 'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Tindakan kepegawaian tersimpan untuk proses persetujuan.');
    }

    public function approveAction(Request $request, EmployeeAction $action)
    {
        $this->allow($request, 'lifecycle.approve');
        CompanyAccess::authorize($request->user(), $action->company_id);
        abort_unless($action->status === 'draft', 422, 'Tindakan ini sudah diproses.');
        DB::transaction(function () use ($action, $request) {
            $updates = array_filter([
                'company_id' => $action->to_company_id, 'department_id' => $action->to_department_id,
                'position_id' => $action->to_position_id, 'job_grade_id' => $action->to_job_grade_id,
            ], fn ($value) => $value !== null);
            if (in_array($action->action_type, ['termination', 'retirement'], true)) {
                $updates += ['status' => 'inactive', 'exit_date' => $action->effective_date, 'end_date' => $action->effective_date];
            }
            $action->employee->update($updates + ['updated_by' => $request->user()->id]);
            $action->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'changes' => $updates]);
        });

        return back()->with('success', 'Tindakan disetujui dan perubahan efektif diterapkan.');
    }

    public function cases(Request $request)
    {
        $this->allow($request, 'hc_case.view');
        $query = HcCase::with(['company', 'employee', 'owner', 'tasks.assignee'])
            ->whereIn('company_id', CompanyAccess::ids($request->user()))
            ->when($request->case_type, fn ($q, $v) => $q->where('case_type', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v));

        return view('enterprise.cases', $this->lookups($request) + ['cases' => $query->latest()->paginate(15)->withQueryString(), 'users' => $this->accessibleUsers($request)->get()]);
    }

    public function storeCase(Request $request)
    {
        $this->allow($request, 'hc_case.create');
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'], 'employee_id' => ['nullable', 'exists:employees,id'],
            'case_type' => ['required', Rule::in(['onboarding', 'offboarding', 'disciplinary', 'grievance', 'medical', 'document', 'claim', 'loan', 'industrial_relation', 'general'])],
            'subject' => ['required', 'string', 'max:200'], 'priority' => ['required', Rule::in(['low', 'normal', 'high', 'critical'])],
            'opened_date' => ['required', 'date'], 'target_date' => ['nullable', 'date', 'after_or_equal:opened_date'],
            'owner_id' => ['nullable', 'exists:users,id'], 'amount' => ['nullable', 'numeric', 'min:0'], 'description' => ['nullable', 'string', 'max:5000'],
        ]);
        CompanyAccess::authorize($request->user(), $data['company_id']);
        if ($data['employee_id'] ?? null) $this->employeeInCompany($request, $data['employee_id'], $data['company_id']);
        if ($data['owner_id'] ?? null) $this->userInCompanyAccess($data['owner_id'], $data['company_id']);
        HcCase::create($data + ['reference_no' => 'HC-'.now()->format('YmdHis').'-'.random_int(100, 999), 'status' => 'open', 'created_by' => $request->user()->id]);

        return back()->with('success', 'Kasus/proses HC berhasil dibuka.');
    }

    public function addCaseTask(Request $request, HcCase $case)
    {
        $this->allow($request, 'hc_case.update');
        CompanyAccess::authorize($request->user(), $case->company_id);
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'assigned_to' => ['nullable', 'exists:users,id'], 'due_date' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:1000']]);
        if ($data['assigned_to'] ?? null) $this->userInCompanyAccess($data['assigned_to'], $case->company_id);
        $case->tasks()->create($data);
        if ($case->status === 'open') $case->update(['status' => 'in_progress']);

        return back()->with('success', 'Checklist proses ditambahkan.');
    }

    public function closeCase(Request $request, HcCase $case)
    {
        $this->allow($request, 'hc_case.update');
        CompanyAccess::authorize($request->user(), $case->company_id);
        if ($case->tasks()->where('status', '!=', 'completed')->exists()) {
            throw ValidationException::withMessages(['case' => 'Semua checklist harus selesai sebelum kasus ditutup.']);
        }
        $case->update(['status' => 'closed', 'closed_date' => today()]);

        return back()->with('success', 'Kasus/proses HC telah ditutup.');
    }

    public function completeCaseTask(Request $request, \App\Models\HcCaseTask $task)
    {
        $this->allow($request, 'hc_case.update');
        CompanyAccess::authorize($request->user(), $task->hcCase->company_id);
        $task->update(['status' => 'completed', 'completed_at' => now()]);

        return back()->with('success', 'Checklist ditandai selesai.');
    }

    public function policies(Request $request)
    {
        $this->allow($request, 'policy.view');

        return view('enterprise.policies', $this->lookups($request) + [
            'calendars' => WorkCalendar::with(['company', 'holidays'])->whereIn('company_id', CompanyAccess::ids($request->user()))->orderBy('name')->get(),
            'flows' => DB::table('approval_flows')->leftJoin('companies', 'companies.id', '=', 'approval_flows.company_id')->select('approval_flows.*', 'companies.name as company_name')->where(fn ($q) => $q->whereNull('company_id')->orWhereIn('company_id', CompanyAccess::ids($request->user())))->orderBy('document_type')->orderBy('step_order')->get(),
        ]);
    }

    public function storeCalendar(Request $request)
    {
        $this->allow($request, 'policy.create');
        $data = $request->validate(['company_id' => ['required', 'exists:companies,id'], 'code' => ['required', 'string', 'max:30'], 'name' => ['required', 'string', 'max:100'], 'timezone' => ['required', 'timezone'], 'working_days' => ['required', 'array', 'min:1'], 'working_days.*' => ['integer', 'between:1,7'], 'is_default' => ['nullable', 'boolean']]);
        CompanyAccess::authorize($request->user(), $data['company_id']);
        $data['is_default'] = $request->boolean('is_default');
        if ($data['is_default']) WorkCalendar::where('company_id', $data['company_id'])->update(['is_default' => false]);
        WorkCalendar::create($data + ['is_active' => true]);

        return back()->with('success', 'Kalender kerja berhasil dibuat.');
    }

    public function storeHoliday(Request $request, WorkCalendar $calendar)
    {
        $this->allow($request, 'policy.create');
        CompanyAccess::authorize($request->user(), $calendar->company_id);
        $data = $request->validate(['holiday_date' => ['required', 'date'], 'name' => ['required', 'string', 'max:150'], 'type' => ['required', Rule::in(['public', 'company', 'collective_leave'])], 'is_paid' => ['nullable', 'boolean']]);
        $calendar->holidays()->updateOrCreate(['holiday_date' => $data['holiday_date']], $data + ['is_paid' => $request->boolean('is_paid')]);

        return back()->with('success', 'Hari libur ditambahkan ke kalender.');
    }

    public function storeFlow(Request $request)
    {
        $this->allow($request, 'policy.create');
        $data = $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'], 'document_type' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'], 'step_order' => ['required', 'integer', 'min:1', 'max:20'],
            'approver_type' => ['required', Rule::in(['role', 'line_manager', 'specific_user'])],
            'approver_role' => ['nullable', 'string', 'max:100'], 'approver_user_id' => ['nullable', 'exists:users,id'],
            'minimum_amount' => ['nullable', 'numeric', 'min:0'], 'maximum_amount' => ['nullable', 'numeric', 'gte:minimum_amount'],
            'sla_hours' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($data['company_id'] ?? null) CompanyAccess::authorize($request->user(), $data['company_id']);
        DB::table('approval_flows')->insert($data + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Tahap workflow berhasil dikonfigurasi.');
    }

    public function assets(Request $request)
    {
        $this->allow($request, 'asset.view');
        $assets = Asset::with(['company', 'activeAssignment.employee'])->whereIn('company_id', CompanyAccess::ids($request->user()))->latest()->paginate(20);

        return view('enterprise.assets', $this->lookups($request) + compact('assets'));
    }

    public function storeAsset(Request $request)
    {
        $this->allow($request, 'asset.create');
        $data = $request->validate(['company_id' => ['required', 'exists:companies,id'], 'asset_code' => ['required', 'string', 'max:60', 'unique:assets,asset_code'], 'name' => ['required', 'string', 'max:150'], 'category' => ['required', 'string', 'max:50'], 'serial_number' => ['nullable', 'string', 'max:100'], 'purchase_date' => ['nullable', 'date'], 'purchase_value' => ['nullable', 'numeric', 'min:0'], 'condition' => ['required', Rule::in(['new', 'good', 'fair', 'damaged'])]]);
        CompanyAccess::authorize($request->user(), $data['company_id']);
        Asset::create($data + ['status' => 'available']);

        return back()->with('success', 'Aset berhasil didaftarkan.');
    }

    public function assignAsset(Request $request, Asset $asset)
    {
        $this->allow($request, 'asset.update');
        CompanyAccess::authorize($request->user(), $asset->company_id);
        abort_unless($asset->status === 'available', 422, 'Aset sedang tidak tersedia.');
        $data = $request->validate(['employee_id' => ['required', 'exists:employees,id'], 'assigned_date' => ['required', 'date'], 'expected_return_date' => ['nullable', 'date', 'after_or_equal:assigned_date'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $this->employeeInCompany($request, $data['employee_id'], $asset->company_id);
        DB::transaction(function () use ($asset, $data, $request) {
            $asset->assignments()->create($data + ['condition_out' => $asset->condition, 'status' => 'assigned', 'assigned_by' => $request->user()->id]);
            $asset->update(['status' => 'assigned']);
        });

        return back()->with('success', 'Aset diserahterimakan kepada karyawan.');
    }

    public function returnAsset(Request $request, AssetAssignment $assignment)
    {
        $this->allow($request, 'asset.update');
        CompanyAccess::authorize($request->user(), $assignment->asset->company_id);
        $data = $request->validate(['returned_date' => ['required', 'date', 'after_or_equal:assigned_date'], 'condition_in' => ['required', Rule::in(['good', 'fair', 'damaged', 'lost'])], 'notes' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($assignment, $data) {
            $assignment->update($data + ['status' => 'returned']);
            $assignment->asset->update(['status' => $data['condition_in'] === 'lost' ? 'lost' : 'available', 'condition' => $data['condition_in']]);
        });

        return back()->with('success', 'Pengembalian aset berhasil dicatat.');
    }

    public function talent(Request $request)
    {
        $this->allowAny($request, ['performance.view', 'learning.view', 'career.view']);
        $ids = CompanyAccess::ids($request->user());

        return view('enterprise.talent', $this->lookups($request) + [
            'cycles' => PerformanceCycle::with(['company'])->withCount('reviews')->whereIn('company_id', $ids)->latest()->get(),
            'programs' => LearningProgram::with(['company'])->withCount('participants')->whereIn('company_id', $ids)->latest()->get(),
            'successions' => SuccessionPlan::with(['company', 'position', 'candidate'])->whereIn('company_id', $ids)->latest()->get(),
            'positions' => Position::with('department.company')->whereHas('department', fn ($q) => $q->whereIn('company_id', $ids))->orderBy('name')->get(),
        ]);
    }

    public function storeCycle(Request $request)
    {
        $this->allow($request, 'performance.create');
        $data = $request->validate(['company_id' => ['required', 'exists:companies,id'], 'name' => ['required', 'string', 'max:120'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after:start_date'], 'goal_weight' => ['required', 'integer', 'between:0,100'], 'competency_weight' => ['required', 'integer', 'between:0,100']]);
        CompanyAccess::authorize($request->user(), $data['company_id']);
        if ($data['goal_weight'] + $data['competency_weight'] !== 100) throw ValidationException::withMessages(['goal_weight' => 'Total bobot harus tepat 100%.']);
        PerformanceCycle::create($data + ['status' => 'draft']);

        return back()->with('success', 'Siklus performance dibuat.');
    }

    public function launchCycle(Request $request, PerformanceCycle $cycle)
    {
        $this->allow($request, 'performance.update');
        CompanyAccess::authorize($request->user(), $cycle->company_id);
        abort_unless($cycle->status === 'draft', 422);
        DB::transaction(function () use ($cycle) {
            Employee::where('company_id', $cycle->company_id)->where('status', 'active')->each(fn ($employee) => PerformanceReview::firstOrCreate(['performance_cycle_id' => $cycle->id, 'employee_id' => $employee->id], ['reviewer_id' => $employee->manager_id, 'status' => 'goal_setting']));
            $cycle->update(['status' => 'active']);
        });

        return back()->with('success', 'Siklus diluncurkan dan review seluruh karyawan aktif dibuat.');
    }

    public function storeLearning(Request $request)
    {
        $this->allow($request, 'learning.create');
        $data = $request->validate(['company_id' => ['required', 'exists:companies,id'], 'code' => ['required', 'string', 'max:40'], 'name' => ['required', 'string', 'max:150'], 'type' => ['required', Rule::in(['training', 'certification', 'workshop', 'coaching', 'elearning'])], 'provider' => ['nullable', 'string', 'max:150'], 'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'], 'budget' => ['nullable', 'numeric', 'min:0'], 'capacity' => ['nullable', 'integer', 'min:1'], 'objectives' => ['nullable', 'string', 'max:2000']]);
        CompanyAccess::authorize($request->user(), $data['company_id']);
        LearningProgram::create($data + ['status' => 'planned']);

        return back()->with('success', 'Program learning dibuat.');
    }

    public function addParticipant(Request $request, LearningProgram $program)
    {
        $this->allow($request, 'learning.update');
        CompanyAccess::authorize($request->user(), $program->company_id);
        $data = $request->validate(['employee_id' => ['required', 'exists:employees,id']]);
        $this->employeeInCompany($request, $data['employee_id'], $program->company_id);
        if ($program->capacity && $program->participants()->count() >= $program->capacity) throw ValidationException::withMessages(['employee_id' => 'Kapasitas program sudah penuh.']);
        LearningParticipant::firstOrCreate(['learning_program_id' => $program->id, 'employee_id' => $data['employee_id']]);

        return back()->with('success', 'Peserta ditambahkan ke program.');
    }

    public function storeSuccession(Request $request)
    {
        $this->allow($request, 'career.create');
        $data = $request->validate(['company_id' => ['required', 'exists:companies,id'], 'position_id' => ['required', 'exists:positions,id'], 'candidate_employee_id' => ['required', 'exists:employees,id'], 'readiness' => ['required', Rule::in(['ready_now', 'one_year', 'two_years', 'long_term'])], 'risk_of_loss' => ['nullable', Rule::in(['low', 'medium', 'high'])], 'performance_box' => ['nullable', 'string', 'max:20'], 'priority' => ['required', 'integer', 'between:1,5'], 'development_plan' => ['nullable', 'string', 'max:2000']]);
        CompanyAccess::authorize($request->user(), $data['company_id']);
        $this->employeeInCompany($request, $data['candidate_employee_id'], $data['company_id']);
        abort_unless(Position::whereKey($data['position_id'])->whereHas('department', fn ($q) => $q->where('company_id', $data['company_id']))->exists(), 422);
        SuccessionPlan::updateOrCreate(['position_id' => $data['position_id'], 'candidate_employee_id' => $data['candidate_employee_id']], $data + ['status' => 'active']);

        return back()->with('success', 'Kandidat suksesi berhasil dipetakan.');
    }

    public function reports(Request $request)
    {
        $this->allow($request, 'report.view');
        $ids = CompanyAccess::ids($request->user());
        $companies = Company::whereIn('id', $ids)->withCount(['employees' => fn ($q) => $q->where('status', 'active')])->get();
        $contractsExpiring = EmploymentContract::with(['company', 'employee'])->whereIn('company_id', $ids)->where('status', 'active')->whereBetween('end_date', [today(), today()->addDays(90)])->orderBy('end_date')->get();
        $openCases = HcCase::whereIn('company_id', $ids)->whereNot('status', 'closed')->selectRaw('case_type, count(*) total')->groupBy('case_type')->pluck('total', 'case_type');

        return view('enterprise.reports', compact('companies', 'contractsExpiring', 'openCases'));
    }

    private function lookups(Request $request): array
    {
        $ids = CompanyAccess::ids($request->user());
        return [
            'companies' => Company::whereIn('id', $ids)->where('is_active', true)->orderBy('name')->get(),
            'employees' => Employee::whereIn('company_id', $ids)->where('status', 'active')->orderBy('full_name')->get(),
        ];
    }

    private function employeeInCompany(Request $request, int $employeeId, int $companyId): Employee
    {
        CompanyAccess::authorize($request->user(), $companyId);
        return Employee::whereKey($employeeId)->where('company_id', $companyId)->firstOrFail();
    }

    private function accessibleUsers(Request $request)
    {
        $ids = CompanyAccess::ids($request->user());

        return User::with(['companies', 'employee'])
            ->where('is_active', true)
            ->where(function ($query) use ($ids) {
                $query->whereHas('roles', fn ($role) => $role->where('name', 'super_admin'))
                    ->orWhereHas('employee', fn ($employee) => $employee->whereIn('company_id', $ids))
                    ->orWhereHas('companies', fn ($company) => $company->whereIn('companies.id', $ids));
            })
            ->orderBy('name');
    }

    private function userInCompanyAccess(int $userId, int $companyId): User
    {
        $user = User::with('employee')->findOrFail($userId);
        $hasAccess = $user->hasRole('super_admin')
            || ($user->employee && (int) $user->employee->company_id === $companyId)
            || $user->companies()->where('companies.id', $companyId)->exists();

        abort_unless($hasAccess, 422, 'PIC yang dipilih tidak memiliki akses ke perusahaan kasus ini.');

        return $user;
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasRole('super_admin') || $request->user()->can($permission), 403);
    }

    private function allowAny(Request $request, array $permissions): void
    {
        abort_unless($request->user()->hasRole('super_admin') || collect($permissions)->contains(fn ($permission) => $request->user()->can($permission)), 403);
    }
}
