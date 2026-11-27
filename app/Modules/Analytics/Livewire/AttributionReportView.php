<?php

namespace App\Modules\Analytics\Livewire;

use App\Modules\Analytics\Services\ROASAggregator;
use App\Modules\Tracking\Services\AttributionCalculationService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class AttributionReportView extends Component
{
    public string $model = 'last_touch';

    public function render()
    {
        Gate::authorize('analytics.view');

        $safeModel = in_array($this->model, AttributionCalculationService::MODELS, true)
            ? $this->model
            : 'last_touch';

        $report = app(ROASAggregator::class)->aggregate($safeModel);

        return view('livewire.analytics.attribution-report-view', [
            'report' => $report,
        ]);
    }
}
