<?php

namespace App\Modules\Leads\Livewire;

use App\Modules\Leads\Models\Lead;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class LeadDetailModal extends Component
{
    use AuthorizesRequests;

    public ?int $leadId = null;

    public string $type = 'B2C';

    public string $status = 'new';

    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    public string $phone = '';

    public string $companyName = '';

    public string $jobTitle = '';

    public string $location = '';

    public ?string $statusMessage = null;

    #[On('open-lead-detail')]
    public function loadLead(int $leadId): void
    {
        $lead = Lead::query()->findOrFail($leadId);
        $this->authorize('view', $lead);

        $this->leadId = (int) $lead->id;
        $this->type = $lead->type;
        $this->status = $lead->status;
        $this->firstName = $lead->first_name;
        $this->lastName = $lead->last_name ?? '';
        $this->email = $lead->email ?? '';
        $this->phone = $lead->phone ?? '';
        $this->companyName = $lead->company_name ?? '';
        $this->jobTitle = $lead->job_title ?? '';
        $this->location = $lead->location ?? '';
        $this->statusMessage = null;
    }

    public function saveLead(): void
    {
        if ($this->leadId === null) {
            return;
        }

        $lead = Lead::query()->findOrFail($this->leadId);
        $this->authorize('update', $lead);

        $validated = $this->validate([
            'status' => ['required', Rule::in(['new', 'contacted', 'qualified', 'unqualified'])],
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'companyName' => [
                Rule::requiredIf(fn (): bool => $lead->type === 'B2B'),
                'nullable',
                'string',
                'max:191',
            ],
            'jobTitle' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:191'],
        ]);

        $lead->update([
            'status' => $validated['status'],
            'first_name' => $validated['firstName'],
            'last_name' => $validated['lastName'] !== '' ? $validated['lastName'] : null,
            'email' => $validated['email'] !== '' ? $validated['email'] : null,
            'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
            'company_name' => $lead->type === 'B2B' ? ($validated['companyName'] ?: null) : null,
            'job_title' => $validated['jobTitle'] !== '' ? $validated['jobTitle'] : null,
            'location' => $validated['location'] !== '' ? $validated['location'] : null,
        ]);

        $this->statusMessage = 'Lead details updated.';
        $this->dispatch('lead-updated');
    }

    public function closeModal(): void
    {
        $this->reset(['leadId', 'statusMessage']);
    }

    public function render(): View
    {
        $lead = $this->leadId !== null
            ? Lead::query()->with(['assignedUser', 'touchpoints.trackingLink'])->find($this->leadId)
            : null;

        return view('livewire.leads.lead-detail-modal', [
            'lead' => $lead,
        ]);
    }
}
