<?php

namespace App\Modules\CustomFields\Services;

use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CustomFieldRegistry
{
    public function cacheKey(int $organizationId, string $entityType): string
    {
        return "custom_fields:v1:{$organizationId}:{$entityType}";
    }

    /**
     * @return Collection<int, CustomFieldDefinition>
     */
    public function forEntity(string $entityType, ?int $organizationId = null): Collection
    {
        $orgId = $organizationId ?? app(TenantContext::class)->getTenantId();

        if (! $orgId || ! in_array($entityType, CustomFieldDefinition::ENTITY_TYPES, true)) {
            return collect();
        }

        // Store plain array rows in cache because config/cache.php sets serializable_classes => false.
        $rows = Cache::remember(
            $this->cacheKey((int) $orgId, $entityType),
            now()->addHour(),
            function () use ($orgId, $entityType): array {
                return CustomFieldDefinition::withoutGlobalScope(OrganizationScope::class)
                    ->where('organization_id', (int) $orgId)
                    ->where('entity_type', $entityType)
                    ->orderBy('order_index')
                    ->orderBy('id')
                    ->get()
                    ->map(static fn (CustomFieldDefinition $def): array => [
                        'id' => (int) $def->id,
                        'organization_id' => (int) $def->organization_id,
                        'entity_type' => (string) $def->entity_type,
                        'field_key' => (string) $def->field_key,
                        'label' => (string) $def->label,
                        'data_type' => (string) $def->data_type,
                        'validation_rules' => $def->validation_rules,
                        'options' => $def->options,
                        'is_required' => (bool) $def->is_required,
                        'order_index' => (int) $def->order_index,
                    ])
                    ->values()
                    ->all();
            },
        );

        return collect($rows)->map(static function (array $row): CustomFieldDefinition {
            $definition = new CustomFieldDefinition;
            $definition->forceFill($row);
            $definition->exists = true;
            $definition->syncOriginal();

            return $definition;
        });
    }

    public function forget(int $organizationId, ?string $entityType = null): void
    {
        if ($entityType !== null) {
            Cache::forget($this->cacheKey($organizationId, $entityType));

            return;
        }

        foreach (CustomFieldDefinition::ENTITY_TYPES as $type) {
            Cache::forget($this->cacheKey($organizationId, $type));
        }
    }

    /**
     * @return array<string, Collection<int, CustomFieldDefinition>>
     */
    public function allForOrganization(?int $organizationId = null): array
    {
        $result = [];

        foreach (CustomFieldDefinition::ENTITY_TYPES as $entityType) {
            $result[$entityType] = $this->forEntity($entityType, $organizationId);
        }

        return $result;
    }
}
