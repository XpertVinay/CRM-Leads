<?php

namespace App\Modules\Communications\WhatsApp\Models;

use App\Modules\Tenancy\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppTemplate extends Model
{
    use BelongsToOrganization;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'organization_id',
        'whatsapp_account_id',
        'name',
        'language',
        'category',
        'header_type',
        'body_text',
        'parameter_placeholders',
        'meta_status',
    ];

    protected function casts(): array
    {
        return [
            'parameter_placeholders' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function isApproved(): bool
    {
        return strtoupper($this->meta_status) === 'APPROVED';
    }
}
