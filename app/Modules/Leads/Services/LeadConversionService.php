<?php

namespace App\Modules\Leads\Services;

use App\Models\Organization;
use App\Modules\Leads\DTOs\LeadConversionData;
use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Pipeline\Models\Pipeline;
use App\Modules\Pipeline\Models\PipelineStage;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use App\Modules\Tracking\Models\Touchpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeadConversionService
{
    public function __construct(
        private readonly LeadAssignmentService $assignmentService,
    ) {}

    /**
     * @return array{lead: Lead, account: ?Account, contact: Contact, deal: ?Deal}
     */
    public function convert(Lead $lead, LeadConversionData $data): array
    {
        return DB::transaction(function () use ($lead, $data): array {
            /** @var Lead $freshLead */
            $freshLead = Lead::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $lead->organization_id)
                ->lockForUpdate()
                ->findOrFail($lead->id);

            if ($freshLead->isConverted()) {
                throw ValidationException::withMessages([
                    'lead' => ['This lead has already been converted.'],
                ]);
            }

            $organizationId = (int) $freshLead->organization_id;
            $account = null;

            if ($freshLead->isB2B()) {
                $account = $this->resolveOrCreateAccount($freshLead, $data);
            }

            $preferredChannel = ($freshLead->phone && ! $freshLead->email) ? 'phone' : 'email';

            $contact = Contact::create([
                'organization_id' => $organizationId,
                'account_id' => $account?->id,
                'first_name' => $freshLead->first_name,
                'last_name' => $freshLead->last_name,
                'email' => $freshLead->email,
                'phone' => $freshLead->phone,
                'job_title' => $freshLead->job_title,
                'preferred_channel' => $preferredChannel,
            ]);

            $deal = null;
            if ($data->createDeal) {
                $deal = $this->createDealForConversion($freshLead, $contact, $account, $data);
            }

            $freshLead->update([
                'status' => 'converted',
                'converted_contact_id' => $contact->id,
                'converted_account_id' => $account?->id,
                'converted_deal_id' => $deal?->id,
                'converted_at' => now(),
            ]);

            $touchpointUpdate = ['contact_id' => $contact->id];
            if ($deal !== null) {
                $touchpointUpdate['deal_id'] = $deal->id;
            }

            Touchpoint::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $organizationId)
                ->where('lead_id', $freshLead->id)
                ->update($touchpointUpdate);

            return [
                'lead' => $freshLead->fresh(),
                'account' => $account,
                'contact' => $contact,
                'deal' => $deal,
            ];
        });
    }

    private function resolveOrCreateAccount(Lead $lead, LeadConversionData $data): Account
    {
        $organizationId = (int) $lead->organization_id;

        if ($data->accountId !== null) {
            $account = Account::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $organizationId)
                ->find($data->accountId);

            if ($account === null) {
                throw ValidationException::withMessages([
                    'account_id' => ['Selected account does not belong to this organization.'],
                ]);
            }

            return $account;
        }

        $accountName = $data->accountName ?? $lead->company_name;
        if ($accountName === null || trim($accountName) === '') {
            throw ValidationException::withMessages([
                'account_name' => ['An account or company name is required to convert a B2B lead.'],
            ]);
        }

        $normalizedName = trim($accountName);
        $existing = Account::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->get()
            ->first(fn (Account $account): bool => Str::lower(trim((string) $account->name)) === Str::lower($normalizedName));

        if ($existing !== null) {
            return $existing;
        }

        return Account::create([
            'organization_id' => $organizationId,
            'name' => $normalizedName,
            'domain' => $data->accountDomain,
            'industry' => $data->accountIndustry,
            'employee_count' => $lead->employee_count,
        ]);
    }

    private function createDealForConversion(
        Lead $lead,
        Contact $contact,
        ?Account $account,
        LeadConversionData $data,
    ): Deal {
        $organizationId = (int) $lead->organization_id;

        if ($data->pipelineId !== null) {
            $pipeline = Pipeline::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $organizationId)
                ->find($data->pipelineId);

            if ($pipeline === null) {
                throw ValidationException::withMessages([
                    'pipeline_id' => ['Selected pipeline does not belong to this organization.'],
                ]);
            }
        } else {
            $pipeline = Pipeline::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $organizationId)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();

            if ($pipeline === null) {
                throw ValidationException::withMessages([
                    'pipeline_id' => ['No sales pipeline exists for this organization.'],
                ]);
            }
        }

        if ($data->pipelineStageId !== null) {
            $stage = PipelineStage::query()
                ->where('pipeline_id', $pipeline->id)
                ->find($data->pipelineStageId);

            if ($stage === null) {
                throw ValidationException::withMessages([
                    'pipeline_stage_id' => ['Selected stage does not belong to the chosen pipeline.'],
                ]);
            }
        } else {
            $stage = PipelineStage::query()
                ->where('pipeline_id', $pipeline->id)
                ->where('category', 'open')
                ->orderBy('order_index')
                ->orderBy('id')
                ->first()
                ?? PipelineStage::query()
                    ->where('pipeline_id', $pipeline->id)
                    ->orderBy('order_index')
                    ->orderBy('id')
                    ->first();

            if ($stage === null) {
                throw ValidationException::withMessages([
                    'pipeline_stage_id' => ['Selected pipeline has no stages configured.'],
                ]);
            }
        }

        $ownerId = $data->assignedUserId ?? $lead->assigned_user_id;
        if ($ownerId !== null) {
            $this->assignmentService->assertOrganizationMember($organizationId, (int) $ownerId);
        }

        $currency = $data->dealCurrency
            ?: (Organization::query()->where('id', $organizationId)->value('currency') ?? 'USD');

        $defaultTitle = $account !== null
            ? "{$account->name} — {$contact->fullName()}"
            : $contact->fullName();

        $now = now();

        return Deal::create([
            'organization_id' => $organizationId,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'contact_id' => $contact->id,
            'account_id' => $account?->id,
            'assigned_user_id' => $ownerId,
            'title' => $data->dealTitle ?: $defaultTitle,
            'amount' => $data->dealAmount,
            'currency' => strtoupper((string) $currency),
            'expected_close_date' => $data->expectedCloseDate,
            'stage_entered_at' => $now,
            'closed_at' => ($stage->isWon() || $stage->isLost()) ? $now : null,
        ]);
    }
}
