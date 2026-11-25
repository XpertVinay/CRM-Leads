<?php

namespace App\Modules\Communications\WhatsApp\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communications\WhatsApp\Adapters\MetaGraphApiClient;
use App\Modules\Communications\WhatsApp\Jobs\SendWhatsAppTemplateJob;
use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Communications\WhatsApp\Models\WhatsAppMessage;
use App\Modules\Communications\WhatsApp\Models\WhatsAppTemplate;
use App\Modules\Communications\WhatsApp\Services\ConsentService;
use App\Modules\Communications\WhatsApp\Services\WindowEnforcementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WhatsAppChatController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewConversations', WhatsAppAccount::class);

        return view('whatsapp.chat');
    }

    public function conversations(): JsonResponse
    {
        Gate::authorize('viewConversations', WhatsAppAccount::class);

        $conversations = WhatsAppConversation::with('contact:id,first_name,last_name,email,phone')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $conversations]);
    }

    public function messages(WhatsAppConversation $conversation): JsonResponse
    {
        Gate::authorize('viewConversations', WhatsAppAccount::class);

        return response()->json([
            'data' => $conversation->messages()->get(),
            'is_window_active' => $conversation->isWindowActive(),
        ]);
    }

    public function sendText(
        Request $request,
        WhatsAppConversation $conversation,
        WindowEnforcementService $windowEnforcer,
        ConsentService $consentService,
        MetaGraphApiClient $client,
    ): JsonResponse {
        Gate::authorize('sendMessage', $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4096'],
        ]);

        $windowEnforcer->assertCanSendFreeFormText($conversation);
        $consentService->assertOptedIn((int) $conversation->organization_id, (int) $conversation->contact_id);

        $account = $conversation->account;
        $result = $client->sendTextMessage(
            $account->phone_number_id,
            $account->getPlainAccessToken(),
            $conversation->customer_phone,
            $validated['body'],
        );

        $wamid = $result['messages'][0]['id'] ?? null;

        $message = WhatsAppMessage::create([
            'organization_id' => $conversation->organization_id,
            'whatsapp_conversation_id' => $conversation->id,
            'meta_message_id' => $wamid,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => $validated['body'],
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'message' => 'Message sent.',
            'data' => $message,
        ], 201);
    }

    public function sendTemplate(
        Request $request,
        WhatsAppConversation $conversation,
        ConsentService $consentService,
    ): JsonResponse {
        Gate::authorize('sendMessage', $conversation);

        $validated = $request->validate([
            'template_id' => ['nullable', 'integer'],
            'template_name' => ['nullable', 'string', 'max:100'],
            'parameters' => ['nullable', 'array'],
            'parameters.*' => ['string'],
        ]);

        $consentService->assertOptedIn((int) $conversation->organization_id, (int) $conversation->contact_id);

        $template = null;
        if (! empty($validated['template_id'])) {
            $template = WhatsAppTemplate::find($validated['template_id']);
        } elseif (! empty($validated['template_name'])) {
            $template = WhatsAppTemplate::where('name', $validated['template_name'])->first();
        }

        if (! $template || ! $template->isApproved()) {
            throw ValidationException::withMessages([
                'template' => ['An approved WhatsApp template is required.'],
            ]);
        }

        $parameters = $validated['parameters'] ?? [];
        $renderedBody = $template->body_text;
        foreach (array_values($parameters) as $idx => $val) {
            $renderedBody = str_replace('{{'.($idx + 1).'}}', $val, $renderedBody);
        }

        $message = WhatsAppMessage::create([
            'organization_id' => $conversation->organization_id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'message_type' => 'template',
            'body' => $renderedBody,
            'template_name' => $template->name,
            'status' => 'queued',
            'sent_at' => now(),
        ]);

        SendWhatsAppTemplateJob::dispatch(
            $message->id,
            $conversation->id,
            $template->id,
            array_values($parameters),
        );

        return response()->json([
            'message' => 'Template message queued.',
            'data' => $message,
        ], 201);
    }
}
