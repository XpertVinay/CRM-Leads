<?php

namespace App\Modules\CustomFields\Models;

use App\Modules\CustomFields\Services\CustomFieldRegistry;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomFieldDefinition extends Model
{
    use BelongsToOrganization, HasFactory;

    public const ENTITY_TYPES = ['lead', 'contact', 'account', 'deal'];

    public const DATA_TYPES = ['text', 'number', 'date', 'boolean', 'select', 'multiselect', 'textarea'];

    protected $table = 'custom_field_definitions';

    protected $fillable = [
        'organization_id',
        'entity_type',
        'field_key',
        'label',
        'data_type',
        'validation_rules',
        'options',
        'is_required',
        'order_index',
    ];

    protected function casts(): array
    {
        return [
            'validation_rules' => 'array',
            'options' => 'array',
            'is_required' => 'boolean',
            'order_index' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CustomFieldDefinition $definition): void {
            $definition->field_key = Str::lower(trim((string) $definition->field_key));
        });

        static::saved(function (CustomFieldDefinition $definition): void {
            if ($definition->organization_id && $definition->entity_type) {
                app(CustomFieldRegistry::class)->forget(
                    (int) $definition->organization_id,
                    (string) $definition->entity_type,
                );
            }
        });

        static::deleted(function (CustomFieldDefinition $definition): void {
            if ($definition->organization_id && $definition->entity_type) {
                app(CustomFieldRegistry::class)->forget(
                    (int) $definition->organization_id,
                    (string) $definition->entity_type,
                );
            }
        });
    }

    /**
     * @return array<int, string>
     */
    public function normalizedOptions(): array
    {
        $raw = $this->options ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $options = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $candidate = $item['value'] ?? $item['label'] ?? null;
                if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                    $options[] = (string) $candidate;
                }
            } elseif (is_scalar($item) && trim((string) $item) !== '') {
                $options[] = (string) $item;
            }
        }

        return array_values(array_unique($options));
    }
}
