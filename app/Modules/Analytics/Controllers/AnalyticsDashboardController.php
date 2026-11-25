<?php

namespace App\Modules\Analytics\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\FunnelMetricsCalculator;
use App\Modules\Analytics\Services\ProductivityMetricsService;
use App\Modules\Analytics\Services\ROASAggregator;
use App\Modules\Analytics\Services\WhatsAppHealthService;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsDashboardController extends Controller
{
    public function executive(FunnelMetricsCalculator $calculator): View
    {
        return view('analytics.executive', [
            'metrics' => $calculator->calculate(),
        ]);
    }

    public function executiveJson(FunnelMetricsCalculator $calculator): JsonResponse
    {
        return response()->json(['data' => $calculator->calculate()]);
    }

    public function attribution(Request $request, ROASAggregator $aggregator): View
    {
        $model = (string) $request->query('model', 'last_touch');

        return view('analytics.attribution', [
            'report' => $aggregator->aggregate($model),
        ]);
    }

    public function attributionJson(Request $request, ROASAggregator $aggregator): JsonResponse
    {
        $model = (string) $request->query('model', 'last_touch');
        if (! in_array($model, ['first_touch', 'last_touch', 'linear'], true)) {
            $model = 'last_touch';
        }

        return response()->json(['data' => $aggregator->aggregate($model)]);
    }

    public function productivity(ProductivityMetricsService $service): View
    {
        return view('analytics.productivity', [
            'reps' => $service->perRepMetrics(),
        ]);
    }

    public function productivityJson(ProductivityMetricsService $service): JsonResponse
    {
        return response()->json(['data' => $service->perRepMetrics()]);
    }

    public function whatsappHealth(WhatsAppHealthService $service): View
    {
        return view('analytics.whatsapp-health', [
            'health' => $service->metrics(),
        ]);
    }

    public function whatsappHealthJson(WhatsAppHealthService $service): JsonResponse
    {
        return response()->json(['data' => $service->metrics()]);
    }
}
