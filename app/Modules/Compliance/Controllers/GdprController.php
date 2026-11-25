<?php

namespace App\Modules\Compliance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Models\AuditLog;
use App\Modules\Compliance\Services\GdprComplianceService;
use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Contact;
use Illuminate\Http\JsonResponse;
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
}
