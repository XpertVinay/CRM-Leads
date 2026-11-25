<?php

namespace App\Modules\Tenancy\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Services\AuditService;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\OrganizationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationSettingsController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        $organization = $tenantContext->getTenant();
        Gate::authorize('update', $organization);

        $settings = OrganizationSetting::forOrganization($organization);

        return view('settings.organization', [
            'organization' => $organization,
            'settings' => $settings,
            'duplicateStrategies' => OrganizationSetting::DUPLICATE_STRATEGIES,
        ]);
    }

    public function update(Request $request, TenantContext $tenantContext, AuditService $auditService): JsonResponse
    {
        $organization = $tenantContext->getTenant();
        Gate::authorize('update', $organization);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'timezone' => ['sometimes', 'required', 'string', 'max:50', 'timezone:all'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'whatsapp_phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{6,32}$/'],
            'whatsapp_default_message' => ['nullable', 'string', 'max:1000'],
            'duplicate_lead_strategy' => ['required', 'string', Rule::in(OrganizationSetting::DUPLICATE_STRATEGIES)],
            'capture_ip_address' => ['boolean'],
            'lead_reference_prefix' => ['required', 'string', 'max:16', 'regex:/^[A-Za-z0-9]+$/'],
            'require_email' => ['boolean'],
            'require_company' => ['boolean'],
        ]);

        if (isset($validated['name']) || isset($validated['timezone']) || isset($validated['currency'])) {
            $organization->update(array_filter([
                'name' => $validated['name'] ?? null,
                'timezone' => $validated['timezone'] ?? null,
                'currency' => isset($validated['currency']) ? strtoupper($validated['currency']) : null,
            ], static fn ($v) => $v !== null));
        }

        $settings = OrganizationSetting::forOrganization($organization);
        $settings->update([
            'whatsapp_phone' => $validated['whatsapp_phone'] ?? null,
            'whatsapp_default_message' => $validated['whatsapp_default_message'] ?? $settings->whatsapp_default_message,
            'duplicate_lead_strategy' => $validated['duplicate_lead_strategy'],
            'capture_ip_address' => (bool) ($validated['capture_ip_address'] ?? false),
            'lead_reference_prefix' => strtoupper($validated['lead_reference_prefix']),
            'require_email' => (bool) ($validated['require_email'] ?? false),
            'require_company' => (bool) ($validated['require_company'] ?? false),
        ]);

        $auditService->record('organization_settings.updated', $organization, null, $settings->toArray());

        return response()->json([
            'message' => 'Organization settings saved successfully.',
            'organization' => $organization->fresh(),
            'settings' => $settings->fresh(),
        ]);
    }
}
