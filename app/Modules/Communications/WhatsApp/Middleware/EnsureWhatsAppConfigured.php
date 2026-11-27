<?php

namespace App\Modules\Communications\WhatsApp\Middleware;

use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Tenancy\Context\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWhatsAppConfigured
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->getTenant();

        if (! $tenant || ! WhatsAppAccount::isConfiguredForOrganization((int) $tenant->id)) {
            abort(403, 'WhatsApp API integration is not configured for this organization.');
        }

        return $next($request);
    }
}
