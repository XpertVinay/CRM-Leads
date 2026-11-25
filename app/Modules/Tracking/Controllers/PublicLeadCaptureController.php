<?php

namespace App\Modules\Tracking\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Modules\CustomFields\Services\CustomFieldValidationService;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadStatusHistory;
use App\Modules\Leads\Services\LeadAssignmentService;
use App\Modules\Pipeline\Models\Activity;
use App\Modules\Tenancy\Models\OrganizationSetting;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use App\Modules\Tracking\Events\TouchpointCreatedEvent;
use App\Modules\Tracking\Models\LinkClick;
use App\Modules\Tracking\Models\Touchpoint;
use App\Modules\Tracking\Models\TrackingLink;
use App\Modules\Tracking\Services\LinkShortenerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicLeadCaptureController extends Controller
{
    private const SOCIAL_PARAM_KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'fbclid',
        'gclid',
        'igshid',
        'ttclid',
        'li_fat_id',
    ];

    public function show(
        Request $request,
        string $slug,
        LinkShortenerService $shortener,
    ): Response {
        $trackingLink = $this->resolveLink($slug);

        if ($trackingLink === null) {
            abort(404);
        }

        if (! $trackingLink->isUsable()) {
            return response()->view('tracking.unavailable', [
                'link' => $trackingLink,
            ], 410);
        }

        $click = $shortener->recordClick($trackingLink, $request);
        $organization = Organization::query()->find($trackingLink->organization_id);
        $settings = OrganizationSetting::forOrganization((int) $trackingLink->organization_id);
        $idempotencyKey = (string) Str::uuid();
        $customFields = $trackingLink->selectedLeadCustomFields();
        $socialParams = $this->extractSocialParams($request);

        return response()
            ->view('tracking.capture', [
                'link' => $trackingLink->load('campaign:id,name'),
                'organization' => $organization,
                'settings' => $settings,
                'idempotencyKey' => $idempotencyKey,
                'customFields' => $customFields,
                'socialParams' => $socialParams,
            ])
            ->withCookie(cookie(
                name: 'click_token',
                value: $click->click_token,
                minutes: 60 * 24 * 30,
                path: '/',
                domain: null,
                secure: $request->isSecure(),
                httpOnly: true,
                raw: false,
                sameSite: 'lax',
            ));
    }

    public function submit(
        Request $request,
        string $slug,
        CustomFieldValidationService $customFieldValidator,
        LeadAssignmentService $assignmentService,
    ): RedirectResponse|Response {
        $trackingLink = $this->resolveLink($slug);

        if ($trackingLink === null) {
            abort(404);
        }

        if (! $trackingLink->isUsable()) {
            return response()->view('tracking.unavailable', [
                'link' => $trackingLink,
            ], 410);
        }

        $settings = OrganizationSetting::forOrganization((int) $trackingLink->organization_id);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'min:6', 'max:50'],
            'email' => [$settings->require_email ? 'required' : 'nullable', 'email', 'max:191'],
            'company_name' => [$settings->require_company ? 'required' : 'nullable', 'string', 'max:191'],
            'requirement' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'custom_fields' => ['nullable', 'array'],
            'social_params' => ['nullable', 'array'],
        ]);

        $selectedCustomFields = $trackingLink->selectedLeadCustomFields();
        $validatedCustomFields = $customFieldValidator->validateDefinitions(
            $selectedCustomFields,
            is_array($validated['custom_fields'] ?? null) ? $validated['custom_fields'] : [],
            'custom_fields',
        );

        $socialParams = $this->sanitizeSocialParams(
            $validated['social_params'] ?? [],
            $request,
        );

        $waPhone = $trackingLink->whatsapp_number ?: $settings->whatsapp_phone;
        $waDigits = preg_replace('/\D+/', '', (string) $waPhone);
        if ($waDigits === '' || strlen($waDigits) < 6) {
            throw ValidationException::withMessages([
                'whatsapp' => ['WhatsApp destination number is not configured for this organization or link.'],
            ]);
        }

        $idemKey = trim((string) ($validated['idempotency_key'] ?? ''));
        if ($idemKey !== '') {
            $cacheKey = 'wa_capture_idem:'.$trackingLink->id.':'.$idemKey;
            if (! Cache::add($cacheKey, true, now()->addMinutes(15))) {
                return back()->withInput()->withErrors([
                    'idempotency' => ['This inquiry has already been submitted.'],
                ]);
            }
        }

        $clickToken = is_string($request->cookie('click_token')) ? $request->cookie('click_token') : null;
        $click = $clickToken !== null
            ? LinkClick::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $trackingLink->organization_id)
                ->where('click_token', $clickToken)
                ->first()
            : null;

        [$firstName, $lastName] = $this->splitFullName($validated['full_name']);
        $normalizedPhone = $this->normalizePhone($validated['phone']);

        /** @var array{lead: Lead, touchpoint: Touchpoint, is_duplicate: bool} $result */
        $result = DB::transaction(function () use (
            $trackingLink,
            $settings,
            $validated,
            $validatedCustomFields,
            $socialParams,
            $assignmentService,
            $firstName,
            $lastName,
            $normalizedPhone,
            $click,
            $waDigits
        ): array {
            $existingLead = $this->findDuplicateLead(
                (int) $trackingLink->organization_id,
                $normalizedPhone,
            );

            $now = now('UTC');
            $isDuplicate = false;

            if ($existingLead !== null && $settings->duplicate_lead_strategy === 'reject') {
                throw ValidationException::withMessages([
                    'phone' => ['A lead with this mobile number already exists.'],
                ]);
            }

            if ($existingLead !== null && $settings->duplicate_lead_strategy === 'merge_history') {
                $isDuplicate = true;
                $mergedCustomFields = array_merge(
                    is_array($existingLead->custom_fields) ? $existingLead->custom_fields : [],
                    $validatedCustomFields,
                );
                $existingLead->update([
                    'duplicate_count' => (int) $existingLead->duplicate_count + 1,
                    'last_inquiry_at' => $now,
                    'email' => $existingLead->email ?: ($validated['email'] ?? null),
                    'company_name' => $existingLead->company_name ?: ($validated['company_name'] ?? null),
                    'requirement' => trim(implode("\n", array_filter([
                        $existingLead->requirement,
                        $validated['requirement'] ?? null,
                    ]))) ?: null,
                    'custom_fields' => $mergedCustomFields !== [] ? $mergedCustomFields : null,
                ]);
                $lead = $existingLead->fresh();

                Activity::create([
                    'organization_id' => (int) $trackingLink->organization_id,
                    'activitable_type' => Lead::class,
                    'activitable_id' => (int) $lead->id,
                    'action' => 'duplicate_inquiry',
                    'description' => sprintf('Repeat WhatsApp inquiry via tracking link "%s"', $trackingLink->name),
                    'metadata' => array_filter([
                        'tracking_link_id' => $trackingLink->id,
                        'campaign_id' => $trackingLink->campaign_id,
                        'requirement' => $validated['requirement'] ?? null,
                        'social_params' => $socialParams !== [] ? $socialParams : null,
                    ], static fn ($v): bool => $v !== null),
                ]);

                Touchpoint::create([
                    'organization_id' => (int) $trackingLink->organization_id,
                    'tracking_link_id' => (int) $trackingLink->id,
                    'link_click_id' => $click?->id,
                    'lead_id' => (int) $lead->id,
                    'event_milestone' => 'duplicate_submission',
                    'occurred_at' => $now,
                    'metadata' => array_filter([
                        'lead_reference' => $lead->lead_reference,
                        'social_params' => $socialParams !== [] ? $socialParams : null,
                    ], static fn ($v): bool => $v !== null),
                    'created_at' => $now,
                ]);
            } else {
                $source = $trackingLink->source
                    ?: $trackingLink->utm_source
                    ?: ($socialParams['utm_source'] ?? null)
                    ?: $trackingLink->channel;

                $lead = Lead::create([
                    'organization_id' => (int) $trackingLink->organization_id,
                    'campaign_id' => $trackingLink->campaign_id,
                    'tracking_link_id' => (int) $trackingLink->id,
                    'type' => ! empty($validated['company_name']) ? 'B2B' : 'B2C',
                    'status' => 'new',
                    'priority' => 'medium',
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $validated['email'] ?? null,
                    'phone' => trim($validated['phone']),
                    'company_name' => $validated['company_name'] ?? null,
                    'requirement' => $validated['requirement'] ?? null,
                    'source' => $source,
                    'last_inquiry_at' => $now,
                    'custom_fields' => $validatedCustomFields !== [] ? $validatedCustomFields : null,
                ]);

                $assigneeId = $assignmentService->resolveAutoAssigneeId(
                    (int) $trackingLink->organization_id,
                    $trackingLink->assigned_user_id ? (int) $trackingLink->assigned_user_id : null,
                    (bool) $trackingLink->auto_assign_round_robin,
                );
                if ($assigneeId !== null) {
                    $lead = $assignmentService->assign($lead, $assigneeId);
                }

                LeadStatusHistory::create([
                    'organization_id' => (int) $trackingLink->organization_id,
                    'lead_id' => (int) $lead->id,
                    'from_status' => null,
                    'to_status' => 'new',
                    'reason' => 'Captured via WhatsApp tracking link',
                    'changed_at' => $now,
                ]);

                Activity::create([
                    'organization_id' => (int) $trackingLink->organization_id,
                    'activitable_type' => Lead::class,
                    'activitable_id' => (int) $lead->id,
                    'action' => 'lead_captured',
                    'description' => sprintf('Lead captured from "%s" (%s)', $trackingLink->name, $source),
                    'metadata' => array_filter([
                        'tracking_link_id' => $trackingLink->id,
                        'campaign_id' => $trackingLink->campaign_id,
                        'utm_source' => $socialParams['utm_source'] ?? $trackingLink->utm_source,
                        'utm_medium' => $socialParams['utm_medium'] ?? $trackingLink->utm_medium,
                        'utm_campaign' => $socialParams['utm_campaign'] ?? $trackingLink->utm_campaign,
                        'utm_term' => $socialParams['utm_term'] ?? $trackingLink->utm_term,
                        'utm_content' => $socialParams['utm_content'] ?? $trackingLink->utm_content,
                        'fbclid' => $socialParams['fbclid'] ?? null,
                        'gclid' => $socialParams['gclid'] ?? null,
                        'igshid' => $socialParams['igshid'] ?? null,
                        'ttclid' => $socialParams['ttclid'] ?? null,
                        'li_fat_id' => $socialParams['li_fat_id'] ?? null,
                        'referer' => $socialParams['referer'] ?? null,
                    ], static fn ($v): bool => $v !== null),
                ]);

                Touchpoint::create([
                    'organization_id' => (int) $trackingLink->organization_id,
                    'tracking_link_id' => (int) $trackingLink->id,
                    'link_click_id' => $click?->id,
                    'lead_id' => (int) $lead->id,
                    'event_milestone' => 'lead_captured',
                    'occurred_at' => $now,
                    'metadata' => array_filter([
                        'source' => $source,
                        'lead_reference' => $lead->lead_reference,
                        'social_params' => $socialParams !== [] ? $socialParams : null,
                    ], static fn ($v): bool => $v !== null),
                    'created_at' => $now,
                ]);
            }

            $redirectTouchpoint = Touchpoint::create([
                'organization_id' => (int) $trackingLink->organization_id,
                'tracking_link_id' => (int) $trackingLink->id,
                'link_click_id' => $click?->id,
                'lead_id' => (int) $lead->id,
                'event_milestone' => 'whatsapp_redirect',
                'occurred_at' => $now,
                'metadata' => [
                    'wa_number' => $waDigits,
                    'lead_reference' => $lead->lead_reference,
                ],
                'created_at' => $now,
            ]);

            return [
                'lead' => $lead,
                'touchpoint' => $redirectTouchpoint,
                'is_duplicate' => $isDuplicate,
            ];
        });

        TouchpointCreatedEvent::dispatch(
            (int) $trackingLink->organization_id,
            (int) $result['touchpoint']->id,
            'whatsapp_redirect',
        );

        $template = $trackingLink->whatsapp_message
            ?: $settings->whatsapp_default_message
            ?: 'Hello, I am {name} (Ref: {lead_ref}).';

        $campaignName = $trackingLink->campaign?->name
            ?: $trackingLink->utm_campaign
            ?: $trackingLink->name;

        $replacements = [
            '{lead_ref}' => (string) $result['lead']->lead_reference,
            '{name}' => $result['lead']->fullName(),
            '{phone}' => (string) $result['lead']->phone,
            '{company}' => (string) ($result['lead']->company_name ?? ''),
            '{campaign}' => (string) $campaignName,
        ];

        $leadCustomFields = is_array($result['lead']->custom_fields) ? $result['lead']->custom_fields : [];
        foreach ($leadCustomFields as $fieldKey => $fieldValue) {
            $replacements['{custom.'.$fieldKey.'}'] = $this->formatCustomFieldForMessage($fieldValue);
        }

        $message = strtr($template, $replacements);

        $waUrl = 'https://wa.me/'.$waDigits.'?text='.rawurlencode($message);

        return redirect()->away($waUrl, 302);
    }

    private function resolveLink(string $slug): ?TrackingLink
    {
        return TrackingLink::withoutGlobalScope(OrganizationScope::class)
            ->where(function ($q) use ($slug): void {
                $q->where('slug', $slug)->orWhere('code', $slug);
            })
            ->first();
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function splitFullName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);
        $first = $parts[0] ?? 'Visitor';
        $last = isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : null;

        return [$first, $last];
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    /**
     * @return array<string, string>
     */
    private function extractSocialParams(Request $request): array
    {
        $params = [];
        foreach (self::SOCIAL_PARAM_KEYS as $key) {
            $value = $request->query($key);
            if (is_string($value) && trim($value) !== '') {
                $params[$key] = mb_substr(trim($value), 0, 255);
            }
        }

        $referer = $request->headers->get('referer');
        if (is_string($referer) && trim($referer) !== '') {
            $params['referer'] = mb_substr(trim($referer), 0, 500);
        }

        return $params;
    }

    /**
     * @param  mixed  $rawParams
     * @return array<string, string>
     */
    private function sanitizeSocialParams(mixed $rawParams, Request $request): array
    {
        $allowedKeys = array_merge(self::SOCIAL_PARAM_KEYS, ['referer']);
        $sanitized = [];

        if (is_array($rawParams)) {
            foreach ($allowedKeys as $key) {
                $value = $rawParams[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $sanitized[$key] = mb_substr(trim($value), 0, $key === 'referer' ? 500 : 255);
                }
            }
        }

        foreach ($this->extractSocialParams($request) as $key => $value) {
            $sanitized[$key] ??= $value;
        }

        return $sanitized;
    }

    private function formatCustomFieldForMessage(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private function findDuplicateLead(int $organizationId, string $normalizedDigits): ?Lead
    {
        if ($normalizedDigits === '') {
            return null;
        }

        $tail = strlen($normalizedDigits) >= 10 ? substr($normalizedDigits, -10) : $normalizedDigits;

        return Lead::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->whereNotNull('phone')
            ->get(['id', 'organization_id', 'lead_reference', 'first_name', 'last_name', 'email', 'phone', 'company_name', 'requirement', 'duplicate_count'])
            ->first(function (Lead $candidate) use ($normalizedDigits, $tail): bool {
                $candDigits = preg_replace('/\D+/', '', (string) $candidate->phone) ?? '';
                if ($candDigits === '') {
                    return false;
                }

                return $candDigits === $normalizedDigits
                    || (strlen($candDigits) >= 10 && substr($candDigits, -10) === $tail);
            });
    }
}
