<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function submit(Model $document, string $type, int $requesterId): ApprovalRequest
    {
        return DB::transaction(function () use ($document, $type, $requesterId) {
            $document->update(['status' => 'submitted']);
            $employee = isset($document->employee_id) ? Employee::with('manager.user')->find($document->employee_id) : null;
            $companyId = $employee?->company_id;
            $request = ApprovalRequest::create(['company_id' => $companyId, 'document_type' => $type, 'reference_id' => $document->getKey(), 'requester_id' => $requesterId, 'status' => 'submitted', 'current_step' => 1, 'submitted_at' => now()]);
            $flows = DB::table('approval_flows')->where('document_type', $type)->where('is_active', true)
                ->where(fn ($q) => $q->where('company_id', $companyId)->orWhereNull('company_id'))
                ->orderByRaw('case when company_id is null then 1 else 0 end')->orderBy('step_order')->get()->unique('step_order')->values();
            if ($flows->isEmpty()) {
                $flows = collect([['step_order' => 1, 'approver_role' => 'manager', 'approver_type' => 'line_manager'], ['step_order' => 2, 'approver_role' => 'admin_hr', 'approver_type' => 'role']])->map(fn ($item) => (object) $item);
            }
            foreach ($flows as $i => $flow) {
                $approverId = $flow->approver_type === 'line_manager' ? $employee?->manager?->user?->id : ($flow->approver_user_id ?? null);
                $request->steps()->create(['step_order' => $flow->step_order, 'approver_role' => $flow->approver_role ?: 'specific_user', 'approver_id' => $approverId, 'status' => $i === 0 ? 'pending' : 'waiting']);
            }

            return $request;
        });
    }

    public function act(ApprovalRequest $request, int $userId, array $roles, string $action, ?string $notes = null): void
    {
        DB::transaction(function () use ($request, $userId, $roles, $action, $notes) {
            $step = $request->steps()->where('step_order', $request->current_step)->firstOrFail();
            if ((int) $step->approver_id !== $userId && ! in_array($step->approver_role, $roles, true) && ! in_array('super_admin', $roles, true)) {
                abort(403);
            }
            $step->update(['approver_id' => $userId, 'status' => $action, 'notes' => $notes, 'acted_at' => now()]);
            if ($action === 'rejected') {
                $request->update(['status' => 'rejected', 'completed_at' => now()]);
                $this->document($request)->update(['status' => 'rejected']);

                return;
            }
            $next = $request->steps()->where('step_order', '>', $step->step_order)->orderBy('step_order')->first();
            if ($next) {
                $next->update(['status' => 'pending']);
                $request->update(['status' => 'in_review', 'current_step' => $next->step_order]);
                $this->document($request)->update(['status' => 'in_review']);

                return;
            }
            $request->update(['status' => 'approved', 'completed_at' => now()]);
            $document = $this->document($request);
            $document->update(['status' => 'approved']);
            if ($document instanceof LeaveRequest && $document->leaveType()->value('deduct_balance')) {
                $balance = LeaveBalance::where(['employee_id' => $document->employee_id, 'leave_type_id' => $document->leave_type_id, 'year' => $document->start_date->year])->lockForUpdate()->first();
                if (! $balance || ($balance->entitled - $balance->used) < $document->days) {
                    throw ValidationException::withMessages(['balance' => 'Saldo cuti tidak mencukupi.']);
                } $balance->increment('used', $document->days);
            }
        });
    }

    private function document(ApprovalRequest $request): Model
    {
        $class = match ($request->document_type) {
            'leave' => LeaveRequest::class,'overtime' => OvertimeRequest::class,default => throw ValidationException::withMessages(['document' => 'Jenis dokumen tidak dikenal.'])
        };

        return $class::findOrFail($request->reference_id);
    }
}
