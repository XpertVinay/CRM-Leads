<?php

namespace App\Modules\Compliance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Models\AuditLog;
use App\Modules\Compliance\Services\GdprComplianceService;
use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GdprController extends Controller
{
    public function exportContact(Contact $contact, GdprComplianceService $gdprService): JsonResponse
    {
        Gate::authorize('view', $contact);

        return response()->json([
            'data' => $gdprService->exportContact($contact),
        ]);
    }

    public function exportLead(Lead $lead, GdprComplianceService $gdprService): JsonResponse
    {
        Gate::authorize('view', $lead);

        return response()->json([
            'data' => $gdprService->exportLead($lead),
        ]);
    }

    public function anonymizeContact(Contact $contact, GdprComplianceService $gdprService): JsonResponse
    {
        Gate::authorize('delete', $contact);

        $anonymized = $gdprService->anonymizeContact($contact);

        return response()->json([
            'message' => 'Contact anonymized in compliance with GDPR.',
            'data' => $anonymized,
        ]);
    }

    public function auditLogs(): View
    {
        return view('compliance.audit-logs');
    }

    public function auditLogsJson(): JsonResponse
    {
        $logs = AuditLog::with('user:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $logs]);
    }

    public function storeAuditLog(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:100'],
            'auditable_type' => ['nullable', 'string', 'max:100'],
            'auditable_id' => ['nullable', 'integer'],
            'ip_address' => ['nullable', 'string', 'max:45'],
        ]);

        $log = AuditLog::create([
            'organization_id' => (int) $tenantContext->getTenantId(),
            'user_id' => $request->user()?->id,
            'action' => $validated['action'],
            'auditable_type' => $validated['auditable_type'] ?? null,
            'auditable_id' => $validated['auditable_id'] ?? null,
            'ip_address' => $validated['ip_address'] ?? $request->ip(),
        ]);

        return response()->json([
            'message' => 'Audit log created.',
            'data' => $log->load('user:id,name,email'),
        ], 201);
    }

    public function updateAuditLog(Request $request, AuditLog $auditLog): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:100'],
            'auditable_type' => ['nullable', 'string', 'max:100'],
            'auditable_id' => ['nullable', 'integer'],
            'ip_address' => ['nullable', 'string', 'max:45'],
        ]);

        $auditLog->update($validated);

        return response()->json([
            'message' => 'Audit log updated.',
            'data' => $auditLog->fresh()->load('user:id,name,email'),
        ]);
    }

    public function destroyAuditLog(AuditLog $auditLog): JsonResponse
    {
        $auditLog->delete();

        return response()->json(['message' => 'Audit log deleted.']);
    }
}
