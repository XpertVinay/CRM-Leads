<?php

namespace App\Modules\Analytics\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\FunnelMetricsCalculator;
use App\Modules\Analytics\Services\ProductivityMetricsService;
use App\Modules\Analytics\Services\ROASAggregator;
use App\Modules\Analytics\Services\WhatsAppHealthService;
use App\Modules\Tracking\Services\AttributionCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AnalyticsDashboardController extends Controller
{
    public function executive(FunnelMetricsCalculator $calculator): View
    {
        Gate::authorize('analytics.view');

        return view('analytics.executive', [
            'metrics' => $calculator->calculate(),
        ]);
    }

    public function executiveJson(FunnelMetricsCalculator $calculator): JsonResponse
    {
        Gate::authorize('analytics.view');

        return response()->json(['data' => $calculator->calculate()]);
    }

    public function attribution(Request $request, ROASAggregator $aggregator): View
    {
        Gate::authorize('analytics.view');

        return view('analytics.attribution', [
            'report' => $aggregator->aggregate($this->resolveAttributionModel($request)),
        ]);
    }

    public function attributionJson(Request $request, ROASAggregator $aggregator): JsonResponse
    {
        Gate::authorize('analytics.view');

        return response()->json([
            'data' => $aggregator->aggregate($this->resolveAttributionModel($request)),
        ]);
    }

    public function productivity(ProductivityMetricsService $service): View
    {
        Gate::authorize('analytics.view');

        return view('analytics.productivity', [
            'reps' => $service->perRepMetrics(),
        ]);
    }

    public function productivityJson(ProductivityMetricsService $service): JsonResponse
    {
        Gate::authorize('analytics.view');

        return response()->json(['data' => $service->perRepMetrics()]);
    }

    public function whatsappHealth(WhatsAppHealthService $service): View
    {
        Gate::authorize('analytics.view');

        return view('analytics.whatsapp-health', [
            'health' => $service->metrics(),
        ]);
    }

    public function whatsappHealthJson(WhatsAppHealthService $service): JsonResponse
    {
        Gate::authorize('analytics.view');

        return response()->json(['data' => $service->metrics()]);
    }

    private function resolveAttributionModel(Request $request): string
    {
        $model = (string) $request->query('model', 'last_touch');

        return in_array($model, AttributionCalculationService::MODELS, true)
            ? $model
            : 'last_touch';
    }
}
