<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Communications\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Communications\WhatsApp\Models\WhatsAppMessage;

class WhatsAppHealthService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        $messages = WhatsAppMessage::all();
        $outbound = $messages->where('direction', 'outbound');
        $totalOutbound = $outbound->count();
        $delivered = $outbound->whereIn('status', ['delivered', 'read'])->count();
        $read = $outbound->where('status', 'read')->count();
        $templateCount = $outbound->where('message_type', 'template')->count();

        $conversations = WhatsAppConversation::all();
        $activeWindows = $conversations->filter(fn (WhatsAppConversation $c) => $c->isWindowActive())->count();
        $expiredWindows = $conversations->count() - $activeWindows;

        return [
            'total_messages' => $messages->count(),
            'outbound_count' => $totalOutbound,
            'delivered_count' => $delivered,
            'read_count' => $read,
            'delivery_rate' => $totalOutbound > 0 ? round(($delivered / $totalOutbound) * 100, 2) : 0.0,
            'read_rate' => $totalOutbound > 0 ? round(($read / $totalOutbound) * 100, 2) : 0.0,
            'active_windows_count' => $activeWindows,
            'expired_windows_count' => $expiredWindows,
            'template_messages_count' => $templateCount,
        ];
    }
}
