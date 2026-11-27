<?php

namespace App\Modules\Pipeline\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pipeline\Models\Activity;
use App\Modules\Pipeline\Services\ActivityTimelineService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    private const MORPH_MAP = [
        'lead' => \App\Modules\Leads\Models\Lead::class,
        'account' => \App\Modules\Pipeline\Models\Account::class,
        'contact' => \App\Modules\Pipeline\Models\Contact::class,
        'deal' => \App\Modules\Pipeline\Models\Deal::class,
    ];

    public function store(
        Request $request,
        ActivityTimelineService $activityService,
    ): JsonResponse {
        $normalized = $request->all();
        if (!isset($normalized['activitable_type']) && isset($normalized['subject_type'])) {
            $normalized['activitable_type'] = $normalized['subject_type'];
        }
        if (!isset($normalized['activitable_id']) && isset($normalized['subject_id'])) {
            $normalized['activitable_id'] = $normalized['subject_id'];
        }
        if (!isset($normalized['action']) && isset($normalized['type'])) {
            $normalized['action'] = $normalized['type'];
        }
        $request->merge($normalized);

        $validated = $request->validate([
            'activitable_type' => ['required', 'string', Rule::in(array_keys(self::MORPH_MAP))],
            'activitable_id' => ['required', 'integer'],
            'action' => ['required', 'string', Rule::in(Activity::ACTIONS)],
            'description' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
        ]);

        $model = $this->resolveActivitable($validated['activitable_type'], (int) $validated['activitable_id']);
        Gate::authorize('update', $model);

        $activity = $activityService->log(
            $model,
            $validated['action'],
            $request->user(),
            $validated['description'] ?? null,
            $validated['metadata'] ?? null,
        );

        return response()->json([
            'message' => 'Activity logged.',
            'data' => $activity,
        ], 201);
    }

    public function list(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'activitable_type' => ['required', 'string', Rule::in(array_keys(self::MORPH_MAP))],
            'activitable_id' => ['required', 'integer'],
        ]);

        $model = $this->resolveActivitable($validated['activitable_type'], (int) $validated['activitable_id']);
        Gate::authorize('view', $model);

        $activities = app(ActivityTimelineService::class)->getTimeline($model);

        return response()->json(['data' => $activities]);
    }

    private function resolveActivitable(string $type, int $id): Model
    {
        $modelClass = self::MORPH_MAP[$type];

        return $modelClass::findOrFail($id);
    }
}
