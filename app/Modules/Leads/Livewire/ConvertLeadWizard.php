<?php

namespace App\Modules\Leads\Livewire;

use App\Modules\Leads\DTOs\LeadConversionData;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Services\LeadConversionService;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Pipeline\Models\Pipeline;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class ConvertLeadWizard extends Component
{
    use AuthorizesRequests;

    public ?int $leadId = null;

    public string $leadType = 'B2C';

    public string $accountId = '';

    public string $accountName = '';

    public bool $createDeal = true;

    public string $dealTitle = '';

    public string $dealAmount = '0';

    public string $pipelineId = '';

    public string $pipelineStageId = '';

    public ?string $statusMessage = null;

    #[On('open-convert-lead')]
    public function openWizard(int $leadId): void
    {
        $lead = Lead::query()->findOrFail($leadId);
        $this->authorize('convert', $lead);

        $this->leadId = (int) $lead->id;
        $this->leadType = $lead->type;
        $this->accountId = '';
        $this->accountName = $lead->company_name ?? '';
        $this->createDeal = true;
        $this->dealTitle = $lead->isB2B() && $lead->company_name
            ? "{$lead->company_name} — {$lead->fullName()}"
            : $lead->fullName();
        $this->dealAmount = '0';

        $defaultPipeline = Pipeline::query()->with('stages')->orderByDesc('is_default')->orderBy('id')->first();
        $this->pipelineId = $defaultPipeline ? (string) $defaultPipeline->id : '';
        $firstOpen = $defaultPipeline?->stages->firstWhere('category', 'open');
        $this->pipelineStageId = $firstOpen ? (string) $firstOpen->id : '';
        $this->statusMessage = null;
    }

    public function convertLead(LeadConversionService $conversionService): void
    {
        if ($this->leadId === null) {
            return;
        }

        $lead = Lead::query()->findOrFail($this->leadId);
        $this->authorize('convert', $lead);

        $validated = $this->validate([
            'accountId' => ['nullable', 'integer'],
            'accountName' => ['nullable', 'string', 'max:191'],
            'createDeal' => ['boolean'],
            'dealTitle' => ['nullable', 'string', 'max:191'],
            'dealAmount' => ['nullable', 'numeric', 'min:0'],
            'pipelineId' => ['nullable', 'integer'],
            'pipelineStageId' => ['nullable', 'integer'],
        ]);

        $conversionService->convert($lead, LeadConversionData::fromArray([
            'account_id' => $validated['accountId'] !== '' ? (int) $validated['accountId'] : null,
            'account_name' => $validated['accountName'] !== '' ? $validated['accountName'] : null,
            'create_deal' => $validated['createDeal'],
            'deal_title' => $validated['dealTitle'] !== '' ? $validated['dealTitle'] : null,
            'deal_amount' => $validated['dealAmount'] !== '' ? (float) $validated['dealAmount'] : 0.0,
            'pipeline_id' => $validated['pipelineId'] !== '' ? (int) $validated['pipelineId'] : null,
            'pipeline_stage_id' => $validated['pipelineStageId'] !== '' ? (int) $validated['pipelineStageId'] : null,
        ]));

        $this->statusMessage = 'Lead converted successfully.';
        $this->leadId = null;
        $this->dispatch('lead-converted');
    }

    public function closeWizard(): void
    {
        $this->reset(['leadId', 'statusMessage']);
    }

    public function render(): View
    {
        $lead = $this->leadId !== null ? Lead::query()->find($this->leadId) : null;
        $accounts = Account::query()->orderBy('name')->get();
        $pipelines = Pipeline::query()->with('stages')->orderByDesc('is_default')->orderBy('id')->get();

        return view('livewire.leads.convert-lead-wizard', [
            'lead' => $lead,
            'accounts' => $accounts,
            'pipelines' => $pipelines,
        ]);
    }
}
