<?php

namespace App\Modules\Pipeline\Services;

use App\Models\User;
use App\Modules\Notifications\Notifications\StaleDealSlaNotification;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Pipeline\Models\PipelineStage;
use App\Modules\Tenancy\Scopes\OrganizationScope;

class StaleDealSlaService
{
    public function detectAndFlagStaleDeals(int $organizationId): int
    {
        $openStages = PipelineStage::query()
            ->whereHas('pipeline', function ($q) use ($organizationId): void {
                $q->withoutGlobalScope(OrganizationScope::class)
                    ->where('organization_id', $organizationId);
            })
            ->where('category', 'open')
            ->whereNotNull('stale_days')
            ->where('stale_days', '>', 0)
            ->get();

        if ($openStages->isEmpty()) {
            return 0;
        }

        $flagged = 0;

        foreach ($openStages as $stage) {
            $threshold = now()->subDays($stage->stale_days);

            Deal::withoutGlobalScope(OrganizationScope::class)
                ->with('assignedUser')
                ->where('organization_id', $organizationId)
                ->where('pipeline_stage_id', $stage->id)
                ->where('is_stale', false)
                ->whereNotNull('stage_entered_at')
                ->where('stage_entered_at', '<', $threshold)
                ->whereNull('closed_at')
                ->chunkById(100, function ($staleDeals) use (&$flagged): void {
                    $ids = $staleDeals->pluck('id')->all();
                    if ($ids === []) {
                        return;
                    }

                    Deal::withoutGlobalScope(OrganizationScope::class)
                        ->whereIn('id', $ids)
                        ->update(['is_stale' => true]);

                    $flagged += count($ids);

                    foreach ($staleDeals as $deal) {
                        if ($deal->assignedUser) {
                            $deal->assignedUser->notify(new StaleDealSlaNotification($deal));
                        }
                    }
                });
        }

        return $flagged;
    }
}
