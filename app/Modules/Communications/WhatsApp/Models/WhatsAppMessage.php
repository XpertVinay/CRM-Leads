<?php

namespace App\Modules\Communications\WhatsApp\Models;

use App\Modules\Tenancy\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    use BelongsToOrganization;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'organization_id',
        'whatsapp_conversation_id',
        'meta_message_id',
        'direction',
        'message_type',
        'body',
        'template_name',
        'status',
        'is_coexistence_echo',
        'error_payload',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'is_coexistence_echo' => 'boolean',
            'error_payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }
}
