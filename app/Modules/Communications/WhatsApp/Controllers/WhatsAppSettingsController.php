<?php

namespace App\Modules\Communications\WhatsApp\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communications\WhatsApp\Adapters\MetaGraphApiClient;
use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Communications\WhatsApp\Models\WhatsAppTemplate;
use App\Modules\Communications\WhatsApp\Services\ConsentService;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WhatsAppSettingsController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageSettings', WhatsAppAccount::class);

        $account = WhatsAppAccount::first();
        $templates = $account ? $account->templates()->orderBy('name')->get() : collect();

        return view('whatsapp.settings', [
            'account' => $account,
            'templates' => $templates,
        ]);
    }

    public function connect(
        Request $request,
        TenantContext $tenantContext,
        MetaGraphApiClient $client,
    ): JsonResponse {
        Gate::authorize('manageSettings', WhatsAppAccount::class);

        $validated = $request->validate([
            'code' => ['nullable', 'string'],
            'waba_id' => ['required', 'string', 'max:100'],
            'phone_number_id' => ['required', 'string', 'max:100'],
            'display_phone_number' => ['nullable', 'string', 'max:50'],
            'verified_name' => ['nullable', 'string', 'max:191'],
            'access_token' => ['nullable', 'string'],
            'webhook_verify_token' => ['nullable', 'string', 'max:100'],
            'is_coexistence_enabled' => ['nullable', 'boolean'],
        ]);

        $plainToken = $validated['access_token'] ?? null;
        $displayPhone = $validated['display_phone_number'] ?? null;
        $verifiedName = $validated['verified_name'] ?? null;
        $qualityRating = null;

        if (! empty($validated['code'])) {
            $oauthData = $client->exchangeCodeForToken($validated['code']);
            $plainToken = (string) ($oauthData['access_token'] ?? '');
            if ($plainToken !== '') {
                $profile = $client->getPhoneNumberProfile($validated['phone_number_id'], $plainToken);
                $displayPhone = $displayPhone ?: ($profile['display_phone_number'] ?? null);
                $verifiedName = $verifiedName ?: ($profile['verified_name'] ?? null);
                $qualityRating = $profile['quality_rating'] ?? null;
                $client->subscribeAppToWaba($validated['waba_id'], $plainToken);
            }
        }

        if (! is_string($plainToken) || trim($plainToken) === '') {
            throw ValidationException::withMessages([
                'access_token' => ['Either an OAuth code or an access token is required.'],
            ]);
        }

        $orgId = (int) $tenantContext->getTenantId();
        $account = WhatsAppAccount::updateOrCreate(
            ['organization_id' => $orgId],
            [
                'waba_id' => $validated['waba_id'],
                'phone_number_id' => $validated['phone_number_id'],
                'display_phone_number' => $displayPhone ?? $validated['phone_number_id'],
                'verified_name' => $verifiedName,
                'quality_rating' => $qualityRating,
                'access_token_encrypted' => Crypt::encryptString($plainToken),
                'webhook_verify_token' => $validated['webhook_verify_token'] ?? Str::random(32),
                'is_coexistence_enabled' => (bool) ($validated['is_coexistence_enabled'] ?? false),
                'is_active' => true,
                'status' => 'connected',
            ],
        );

        return response()->json([
            'message' => 'WhatsApp account connected.',
            'data' => $account,
        ], 201);
    }

    public function disconnect(): JsonResponse
    {
        Gate::authorize('manageSettings', WhatsAppAccount::class);

        $account = WhatsAppAccount::first();
        if ($account) {
            $account->update([
                'is_active' => false,
                'status' => 'disconnected',
            ]);
        }

        return response()->json(['message' => 'WhatsApp account disconnected.']);
    }

    public function templates(): JsonResponse
    {
        Gate::authorize('viewConversations', WhatsAppAccount::class);

        return response()->json([
            'data' => WhatsAppTemplate::orderBy('name')->get(),
        ]);
    }

    public function syncTemplates(MetaGraphApiClient $client): JsonResponse
    {
        Gate::authorize('manageSettings', WhatsAppAccount::class);

        $account = WhatsAppAccount::firstOrFail();
        $remoteTemplates = $client->fetchTemplates($account->waba_id, $account->getPlainAccessToken());

        $synced = [];
        foreach ($remoteTemplates as $tpl) {
            $bodyText = '';
            $headerType = 'none';
            $placeholders = [];

            foreach ($tpl['components'] ?? [] as $component) {
                $compType = strtoupper((string) ($component['type'] ?? ''));
                if ($compType === 'BODY') {
                    $bodyText = (string) ($component['text'] ?? '');
                    if (preg_match_all('/\{\{(\d+)\}\}/', $bodyText, $matches)) {
                        $placeholders = array_map(static fn (string $n): string => "param_{$n}", $matches[1]);
                    }
                } elseif ($compType === 'HEADER' && ! empty($component['format'])) {
                    $headerType = strtolower((string) $component['format']);
                }
            }

            $synced[] = WhatsAppTemplate::updateOrCreate(
                [
                    'whatsapp_account_id' => $account->id,
                    'name' => (string) $tpl['name'],
                    'language' => (string) ($tpl['language'] ?? 'en_US'),
                ],
                [
                    'organization_id' => $account->organization_id,
                    'category' => strtolower((string) ($tpl['category'] ?? 'utility')),
                    'header_type' => $headerType,
                    'body_text' => $bodyText !== '' ? $bodyText : (string) $tpl['name'],
                    'parameter_placeholders' => $placeholders,
                    'meta_status' => strtoupper((string) ($tpl['status'] ?? 'PENDING')),
                ],
            );
        }

        return response()->json([
            'message' => 'Templates synchronized.',
            'data' => $synced,
        ]);
    }

    public function storeConsent(
        Request $request,
        TenantContext $tenantContext,
        ConsentService $consentService,
    ): JsonResponse {
        Gate::authorize('manageConsent', WhatsAppAccount::class);

        $validated = $request->validate([
            'contact_id' => ['required', 'integer'],
            'phone' => ['required', 'string', 'max:50'],
            'is_opted_in' => ['required', 'boolean'],
            'consent_source' => ['required', 'string', 'max:100'],
        ]);

        $contact = Contact::findOrFail($validated['contact_id']);

        $consent = $consentService->recordConsent(
            (int) $tenantContext->getTenantId(),
            $contact->id,
            $validated['phone'],
            (bool) $validated['is_opted_in'],
            $validated['consent_source'],
            $request->ip(),
        );

        return response()->json([
            'message' => 'Consent recorded.',
            'data' => $consent,
        ], 201);
    }

    public function storeTemplate(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $account = WhatsAppAccount::first();
        if (! $account) {
            return response()->json([
                'message' => 'Connect a WhatsApp account before creating templates.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'language' => ['required', 'string', 'max:32'],
            'category' => ['required', 'string', 'in:utility,marketing,authentication'],
            'body_text' => ['required', 'string', 'max:2000'],
            'meta_status' => ['nullable', 'string', 'in:APPROVED,PENDING,REJECTED'],
        ]);

        $template = WhatsAppTemplate::create([
            'organization_id' => (int) $tenantContext->getTenantId(),
            'whatsapp_account_id' => $account->id,
            'name' => $validated['name'],
            'language' => $validated['language'],
            'category' => $validated['category'],
            'header_type' => 'none',
            'body_text' => $validated['body_text'],
            'parameter_placeholders' => [],
            'meta_status' => $validated['meta_status'] ?? 'APPROVED',
        ]);

        return response()->json([
            'message' => 'Template created.',
            'data' => $template,
        ], 201);
    }

    public function updateTemplate(Request $request, WhatsAppTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'language' => ['sometimes', 'required', 'string', 'max:32'],
            'category' => ['sometimes', 'required', 'string', 'in:utility,marketing,authentication'],
            'body_text' => ['sometimes', 'required', 'string', 'max:2000'],
            'meta_status' => ['sometimes', 'required', 'string', 'in:APPROVED,PENDING,REJECTED'],
        ]);

        $template->update($validated);

        return response()->json([
            'message' => 'Template updated.',
            'data' => $template->fresh(),
        ]);
    }

    public function destroyTemplate(WhatsAppTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json(['message' => 'Template deleted.']);
    }
}
