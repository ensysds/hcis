<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use App\Support\CompanyAccess;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function index(Request $r)
    {
        $roles = $r->user()->getRoleNames();
        $items = ApprovalRequest::with(['requester.employee', 'steps'])->whereIn('company_id', CompanyAccess::ids($r->user()))->whereIn('status', ['submitted', 'in_review'])->whereHas('steps', fn ($q) => $q->where('status', 'pending')->where(fn ($step) => $step->where('approver_id', $r->user()->id)->orWhereIn('approver_role', $roles->push('super_admin')->unique())))->latest()->paginate(20);

        return view('approvals.index', compact('items'));
    }

    public function act(Request $r, ApprovalRequest $approvalRequest, ApprovalService $service)
    {
        $data = $r->validate(['action' => 'required|in:approved,rejected', 'notes' => 'nullable|string|max:1000']);
        CompanyAccess::authorize($r->user(), $approvalRequest->company_id);
        $service->act($approvalRequest, $r->user()->id, $r->user()->getRoleNames()->all(), $data['action'], $data['notes'] ?? null);

        return back()->with('success', 'Keputusan persetujuan tersimpan.');
    }
}
