<?php

namespace App\Modules\Pipeline\Livewire;

use App\Modules\Pipeline\Models\Deal;
use App\Modules\Pipeline\Models\Pipeline;
use App\Modules\Pipeline\Services\DealStageTransitionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class DealKanbanBoard extends Component
{
    public ?int $pipelineId = null;

    public function mount(?int $pipelineId = null): void
    {
        Gate::authorize('viewAny', Deal::class);

        if ($pipelineId) {
            $this->pipelineId = $pipelineId;
        } else {
            $pipeline = Pipeline::where('is_default', true)->first()
                ?? Pipeline::first();
            $this->pipelineId = $pipeline?->id;
        }
    }

    public function moveToStage(int $dealId, int $stageId, ?string $lostReason = null): void
    {
        $deal = Deal::findOrFail($dealId);
        Gate::authorize('transitionStage', $deal);

        $stage = \App\Modules\Pipeline\Models\PipelineStage::findOrFail($stageId);

        app(DealStageTransitionService::class)->transition(
            $deal,
            $stage,
            $lostReason,
            auth()->user(),
        );
    }

    public function render()
    {
        $pipeline = $this->pipelineId
            ? Pipeline::with(['stages.deals' => fn ($q) => $q->with('contact', 'assignedUser')])->find($this->pipelineId)
            : null;

        $pipelines = Pipeline::orderByDesc('is_default')->orderBy('name')->get();

        return view('livewire.pipeline.deal-kanban-board', [
            'pipeline' => $pipeline,
            'pipelines' => $pipelines,
        ]);
    }
}
