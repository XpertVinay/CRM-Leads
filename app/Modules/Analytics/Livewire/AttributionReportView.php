<?php

namespace App\Modules\Analytics\Livewire;

use App\Modules\Analytics\Services\ROASAggregator;
use Livewire\Component;

class AttributionReportView extends Component
{
    public string $model = 'last_touch';

    public function render()
    {
        $report = app(ROASAggregator::class)->aggregate($this->model);

        return view('livewire.analytics.attribution-report-view', [
            'report' => $report,
        ]);
    }
}
