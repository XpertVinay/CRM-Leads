<?php

namespace App\Modules\Leads\Livewire;

use App\Modules\Leads\Models\Lead;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class LeadInboxTable extends Component
{
    use AuthorizesRequests;

    public string $search = '';

    public string $typeFilter = '';

    public string $statusFilter = '';

    public ?string $statusMessage = null;

    #[On('lead-updated')]
    #[On('lead-converted')]
    public function refreshList(): void
    {
        // Trigger re-render
    }

    public function openLead(int $leadId): void
    {
        $lead = Lead::query()->findOrFail($leadId);
        $this->authorize('view', $lead);

        $this->dispatch('open-lead-detail', leadId: $leadId);
    }

    public function openConvert(int $leadId): void
    {
        $lead = Lead::query()->findOrFail($leadId);
        $this->authorize('convert', $lead);

        $this->dispatch('open-convert-lead', leadId: $leadId);
    }

    public function deleteLead(int $leadId): void
    {
        $lead = Lead::query()->findOrFail($leadId);
        $this->authorize('delete', $lead);

        $lead->delete();
        $this->statusMessage = 'Lead deleted.';
    }

    public function render(): View
    {
        $this->authorize('viewAny', Lead::class);

        $query = Lead::query()
            ->with('assignedUser:id,name')
            ->orderByDesc('id');

        if ($this->typeFilter !== '' && in_array($this->typeFilter, Lead::TYPES, true)) {
            $query->where('type', $this->typeFilter);
        }

        if ($this->statusFilter !== '' && in_array($this->statusFilter, Lead::STATUSES, true)) {
            $query->where('status', $this->statusFilter);
        }

        $leads = $query->get();

        $searchTerm = Str::lower(trim($this->search));
        if ($searchTerm !== '') {
            $leads = $leads->filter(function (Lead $lead) use ($searchTerm): bool {
                foreach ([
                    $lead->lead_reference,
                    $lead->first_name,
                    $lead->last_name,
                    $lead->email,
                    $lead->phone,
                    $lead->company_name,
                    $lead->location,
                ] as $value) {
                    if (is_string($value) && $value !== '' && Str::contains(Str::lower($value), $searchTerm)) {
                        return true;
                    }
                }

                return false;
            })->values();
        }

        return view('livewire.leads.lead-inbox-table', [
            'leads' => $leads,
            'types' => Lead::TYPES,
            'statuses' => Lead::STATUSES,
        ]);
    }
}
