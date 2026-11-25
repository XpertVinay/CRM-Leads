<?php

namespace App\Modules\Analytics\Livewire;

use App\Modules\Analytics\Services\FunnelMetricsCalculator;
use Livewire\Component;

class FunnelVelocityDashboard extends Component
{
    public ?int $pipelineId = null;

    public function render()
    {
        $metrics = app(FunnelMetricsCalculator::class)->calculate($this->pipelineId);

        return view('livewire.analytics.funnel-velocity-dashboard', [
            'metrics' => $metrics,
        ]);
    }
}
