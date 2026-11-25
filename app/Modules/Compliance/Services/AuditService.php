<?php

namespace App\Modules\Compliance\Services;

use App\Modules\Compliance\Models\AuditLog;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $organizationId = null,
        ?int $userId = null,
    ): AuditLog {
        $orgId = $organizationId
            ?? ($auditable && isset($auditable->organization_id) ? (int) $auditable->organization_id : null)
            ?? $this->tenantContext->getTenantId();

        return AuditLog::create([
            'organization_id' => $orgId,
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent() ? substr((string) request()->userAgent(), 0, 255) : null,
            'created_at' => now(),
        ]);
    }
}
