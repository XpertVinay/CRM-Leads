<?php

namespace App\Modules\CustomFields\Traits;

use App\Modules\CustomFields\Services\CustomFieldValidationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

trait HasCustomFields
{
    public function initializeHasCustomFields(): void
    {
        $this->mergeCasts([
            'custom_fields' => 'array',
        ]);
    }

    public function customFieldEntityType(): string
    {
        if (property_exists($this, 'customFieldEntity') && is_string($this->customFieldEntity)) {
            return $this->customFieldEntity;
        }

        return Str::singular($this->getTable());
    }

    public function getCustomField(string $key, mixed $default = null): mixed
    {
        return data_get($this->custom_fields ?? [], $key, $default);
    }

    public function setCustomField(string $key, mixed $value): static
    {
        $fields = is_array($this->custom_fields) ? $this->custom_fields : [];
        $fields[$key] = $value;
        $this->custom_fields = $fields;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function validateAndFillCustomFields(array $payload, ?int $organizationId = null): static
    {
        $validated = app(CustomFieldValidationService::class)->validate(
            $this->customFieldEntityType(),
            $payload,
            $organizationId ?? ($this->organization_id ?? null),
        );

        $this->custom_fields = $validated;

        return $this;
    }

    public function scopeWhereCustomField(Builder $query, string $key, mixed $value): Builder
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            throw new InvalidArgumentException("Invalid custom field key [{$key}].");
        }

        return $query->where("custom_fields->{$key}", $value);
    }
}
