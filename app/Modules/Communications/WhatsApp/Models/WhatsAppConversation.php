<?php

namespace App\Modules\Communications\WhatsApp\Models;

use App\Modules\Pipeline\Models\Contact;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    use BelongsToOrganization;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'organization_id',
        'whatsapp_account_id',
        'contact_id',
        'customer_phone',
        'last_inbound_at',
        'last_message_at',
    ];

    protected $appends = ['is_window_active'];

    protected function casts(): array
    {
        return [
            'last_inbound_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_conversation_id')
            ->orderBy('sent_at')
            ->orderBy('id');
    }

    public function isWindowActive(): bool
    {
        return $this->last_inbound_at !== null
            && $this->last_inbound_at->greaterThanOrEqualTo(now()->subHours(24));
    }

    public function getIsWindowActiveAttribute(): bool
    {
        return $this->isWindowActive();
    }
}
