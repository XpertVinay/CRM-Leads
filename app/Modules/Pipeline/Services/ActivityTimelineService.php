<?php

namespace App\Modules\Pipeline\Services;

use App\Models\User;
use App\Modules\Pipeline\Models\Activity;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ActivityTimelineService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function log(
        Model $activitable,
        string $action,
        ?User $user = null,
        ?string $description = null,
        ?array $metadata = null,
    ): Activity {
        $organizationId = $activitable->organization_id
            ?? $this->tenantContext->getTenantId();

        return Activity::create([
            'organization_id' => $organizationId,
            'user_id' => $user?->id ?? auth()->id(),
            'activitable_type' => $activitable->getMorphClass(),
            'activitable_id' => $activitable->getKey(),
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    public function getTimeline(Model $activitable, int $limit = 50): Collection
    {
        return Activity::query()
            ->where('activitable_type', $activitable->getMorphClass())
            ->where('activitable_id', $activitable->getKey())
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
