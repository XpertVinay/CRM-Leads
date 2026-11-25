<?php

namespace App\Modules\Pipeline\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pipeline\Models\Pipeline;
use App\Modules\Pipeline\Models\PipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PipelineController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Pipeline::class);

        return view('pipelines.index');
    }

    public function list(): JsonResponse
    {
        Gate::authorize('viewAny', Pipeline::class);

        $pipelines = Pipeline::query()
            ->with(['stages' => fn ($q) => $q->orderBy('order_index')->orderBy('id')])
            ->withCount('deals')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $pipelines]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Pipeline::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'is_default' => ['sometimes', 'boolean'],
            'stages' => ['required', 'array', 'min:1'],
            'stages.*.name' => ['required', 'string', 'max:100'],
            'stages.*.category' => ['required', 'string', Rule::in(PipelineStage::CATEGORIES)],
            'stages.*.win_probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'stages.*.stale_days' => ['nullable', 'integer', 'min:1'],
        ]);

        $pipeline = DB::transaction(function () use ($validated): Pipeline {
            $pipeline = Pipeline::create([
                'name' => $validated['name'],
                'is_default' => $validated['is_default'] ?? false,
            ]);

            foreach ($validated['stages'] as $index => $stageData) {
                PipelineStage::create([
                    'pipeline_id' => $pipeline->id,
                    'name' => $stageData['name'],
                    'order_index' => $index,
                    'category' => $stageData['category'],
                    'win_probability' => $stageData['win_probability'] ?? ($stageData['category'] === 'won' ? 100 : ($stageData['category'] === 'lost' ? 0 : 50)),
                    'stale_days' => $stageData['stale_days'] ?? null,
                ]);
            }

            return $pipeline;
        });

        return response()->json([
            'message' => 'Pipeline created.',
            'data' => $pipeline->load('stages'),
        ], 201);
    }

    public function update(Request $request, Pipeline $pipeline): JsonResponse
    {
        Gate::authorize('update', $pipeline);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $pipeline->update($validated);

        return response()->json([
            'message' => 'Pipeline updated.',
            'data' => $pipeline->fresh('stages'),
        ]);
    }

    public function destroy(Pipeline $pipeline): JsonResponse
    {
        Gate::authorize('delete', $pipeline);

        if ($pipeline->deals()->exists()) {
            throw ValidationException::withMessages([
                'pipeline' => ['Cannot delete a pipeline with active deals. Move or delete the deals first.'],
            ]);
        }

        $pipeline->stages()->delete();
        $pipeline->delete();

        return response()->json(['message' => 'Pipeline deleted.']);
    }
}
