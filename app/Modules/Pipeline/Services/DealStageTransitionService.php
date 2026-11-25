<?php

namespace App\Modules\Pipeline\Services;

use App\Models\User;
use App\Modules\Compliance\Services\AuditService;
use App\Modules\Notifications\Notifications\DealStageChangedNotification;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Pipeline\Models\PipelineStage;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use App\Modules\Tracking\Models\Touchpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DealStageTransitionService
{
    public function __construct(
        private readonly ActivityTimelineService $activityService,
    ) {}

    public function transition(
        Deal $deal,
        PipelineStage $newStage,
        ?string $lostReason = null,
        ?User $actor = null,
    ): Deal {
        if ((int) $newStage->pipeline_id !== (int) $deal->pipeline_id) {
            throw ValidationException::withMessages([
                'pipeline_stage_id' => ['The target stage does not belong to this deal\'s pipeline.'],
            ]);
        }

        if ((int) $newStage->id === (int) $deal->pipeline_stage_id) {
            return $deal;
        }

        if ($newStage->isLost() && (! is_string($lostReason) || trim($lostReason) === '')) {
            throw ValidationException::withMessages([
                'lost_reason' => ['A reason is required when moving a deal to a Lost stage.'],
            ]);
        }

        return DB::transaction(function () use ($deal, $newStage, $lostReason, $actor): Deal {
            $oldStage = $deal->pipelineStage;
            $now = now();

            $updates = [
                'pipeline_stage_id' => $newStage->id,
                'stage_entered_at' => $now,
            ];

            if ($newStage->isWon()) {
                $updates['closed_at'] = $now;
                $updates['lost_reason'] = null;
            } elseif ($newStage->isLost()) {
                $updates['closed_at'] = $now;
                $updates['lost_reason'] = trim($lostReason);
            } else {
                $updates['closed_at'] = null;
                $updates['lost_reason'] = null;
                $updates['is_stale'] = false;
            }

            $deal->update($updates);

            if ($newStage->isWon()) {
                $this->createDealWonTouchpoints($deal, $now);
            }

            $this->activityService->log(
                $deal,
                'stage_change',
                $actor,
                "Stage changed from \"{$oldStage?->name}\" to \"{$newStage->name}\"",
                [
                    'old_stage_id' => $oldStage?->id,
                    'new_stage_id' => $newStage->id,
                    'old_stage_name' => $oldStage?->name,
                    'new_stage_name' => $newStage->name,
                ],
            );

            $fresh = $deal->fresh(['pipelineStage', 'pipeline']);

            if ($fresh->assigned_user_id) {
                $owner = User::find($fresh->assigned_user_id);
                if ($owner) {
                    $owner->notify(new DealStageChangedNotification(
                        $fresh,
                        (string) ($oldStage?->name ?? 'Unknown'),
                        $newStage->name,
                    ));
                }
            }

            app(AuditService::class)->record(
                'deal_stage_changed',
                $fresh,
                ['pipeline_stage_id' => $oldStage?->id, 'stage_name' => $oldStage?->name],
                ['pipeline_stage_id' => $newStage->id, 'stage_name' => $newStage->name],
            );

            return $fresh;
        });
    }

    private function createDealWonTouchpoints(Deal $deal, $occurredAt): void
    {
        $existingTouchpoints = Touchpoint::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $deal->organization_id)
            ->where(function ($q) use ($deal): void {
                $q->where('deal_id', $deal->id)
                    ->orWhere('contact_id', $deal->contact_id);
            })
            ->whereNotNull('tracking_link_id')
            ->where('event_milestone', '!=', 'deal_won')
            ->get();

        $seenLinks = [];
        foreach ($existingTouchpoints as $tp) {
            $linkId = (int) $tp->tracking_link_id;
            if (isset($seenLinks[$linkId])) {
                continue;
            }
            $seenLinks[$linkId] = true;

            Touchpoint::withoutGlobalScope(OrganizationScope::class)->create([
                'organization_id' => (int) $deal->organization_id,
                'tracking_link_id' => $linkId,
                'link_click_id' => $tp->link_click_id,
                'lead_id' => $tp->lead_id,
                'contact_id' => $deal->contact_id,
                'deal_id' => $deal->id,
                'event_milestone' => 'deal_won',
                'occurred_at' => $occurredAt,
                'metadata' => [
                    'deal_title' => $deal->title,
                    'deal_amount' => (float) $deal->amount,
                    'deal_currency' => $deal->currency,
                ],
            ]);
        }
    }
}
