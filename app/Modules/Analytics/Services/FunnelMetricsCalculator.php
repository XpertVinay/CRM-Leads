<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Pipeline\Models\Deal;
use App\Modules\Pipeline\Models\Pipeline;

class FunnelMetricsCalculator
{
    /**
     * @return array<string, mixed>
     */
    public function calculate(?int $pipelineId = null): array
    {
        $pipeline = $pipelineId
            ? Pipeline::with('stages')->find($pipelineId)
            : (Pipeline::with('stages')->where('is_default', true)->first() ?? Pipeline::with('stages')->first());

        $dealsQuery = Deal::with('pipelineStage');
        if ($pipeline) {
            $dealsQuery->where('pipeline_id', $pipeline->id);
        }
        $deals = $dealsQuery->get();

        $totalPipelineValue = 0.0;
        $weightedPipelineValue = 0.0;
        $wonCount = 0;
        $lostCount = 0;
        $openCount = 0;
        $wonRevenue = 0.0;
        $cycleDaysSum = 0.0;

        foreach ($deals as $deal) {
            $amount = (float) $deal->amount;
            $stage = $deal->pipelineStage;
            $category = $stage?->category ?? 'open';
            $probability = (float) ($stage?->win_probability ?? 0);

            if ($category === 'won') {
                $wonCount++;
                $wonRevenue += $amount;
                if ($deal->closed_at && $deal->created_at) {
                    $cycleDaysSum += max(0.0, $deal->created_at->diffInHours($deal->closed_at) / 24.0);
                }
            } elseif ($category === 'lost') {
                $lostCount++;
            } else {
                $openCount++;
                $totalPipelineValue += $amount;
                $weightedPipelineValue += $amount * ($probability / 100.0);
            }
        }

        $closedCount = $wonCount + $lostCount;
        $winRate = $closedCount > 0 ? round(($wonCount / $closedCount) * 100, 2) : 0.0;
        $avgSalesCycleDays = $wonCount > 0 ? round($cycleDaysSum / $wonCount, 2) : 0.0;

        $stageFunnel = [];
        if ($pipeline) {
            foreach ($pipeline->stages as $stage) {
                $stageDeals = $deals->where('pipeline_stage_id', $stage->id);
                $stageFunnel[] = [
                    'stage_id' => $stage->id,
                    'stage_name' => $stage->name,
                    'category' => $stage->category,
                    'deal_count' => $stageDeals->count(),
                    'total_amount' => round((float) $stageDeals->sum('amount'), 2),
                ];
            }
        }

        return [
            'total_pipeline_value' => round($totalPipelineValue, 2),
            'weighted_pipeline_value' => round($weightedPipelineValue, 2),
            'won_revenue' => round($wonRevenue, 2),
            'win_rate' => $winRate,
            'open_count' => $openCount,
            'won_count' => $wonCount,
            'lost_count' => $lostCount,
            'avg_sales_cycle_days' => $avgSalesCycleDays,
            'stage_funnel' => $stageFunnel,
        ];
    }
}
