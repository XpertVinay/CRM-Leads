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
        $msgAgg = WhatsAppMessage::query()
            ->selectRaw("
                COUNT(*) as total_messages,
                SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound_count,
                SUM(CASE WHEN direction = 'outbound' AND status IN ('delivered', 'read') THEN 1 ELSE 0 END) as delivered_count,
                SUM(CASE WHEN direction = 'outbound' AND status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN direction = 'outbound' AND message_type = 'template' THEN 1 ELSE 0 END) as template_messages_count
            ")
            ->first();

        $totalMessages = (int) ($msgAgg?->total_messages ?? 0);
        $totalOutbound = (int) ($msgAgg?->outbound_count ?? 0);
        $delivered = (int) ($msgAgg?->delivered_count ?? 0);
        $read = (int) ($msgAgg?->read_count ?? 0);
        $templateCount = (int) ($msgAgg?->template_messages_count ?? 0);

        $cutoff = now()->subHours(24);
        $convAgg = WhatsAppConversation::query()
            ->selectRaw('COUNT(*) as total_conversations')
            ->selectRaw('SUM(CASE WHEN last_inbound_at IS NOT NULL AND last_inbound_at >= ? THEN 1 ELSE 0 END) as active_windows_count', [$cutoff])
            ->first();

        $totalConversations = (int) ($convAgg?->total_conversations ?? 0);
        $activeWindows = (int) ($convAgg?->active_windows_count ?? 0);
        $expiredWindows = max(0, $totalConversations - $activeWindows);

        return [
            'total_messages' => $totalMessages,
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
