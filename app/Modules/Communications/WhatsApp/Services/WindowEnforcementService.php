<?php

namespace App\Modules\Communications\WhatsApp\Services;

use App\Modules\Communications\WhatsApp\Models\WhatsAppConversation;
use Illuminate\Validation\ValidationException;

class WindowEnforcementService
{
    public function assertCanSendFreeFormText(WhatsAppConversation $conversation): void
    {
        if (! $conversation->isWindowActive()) {
            throw ValidationException::withMessages([
                'window' => ['The 24-hour customer service window has expired. Please send an approved WhatsApp template message instead.'],
            ]);
        }
    }
}
