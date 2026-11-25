<?php

namespace App\Modules\CustomFields\Livewire;

use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\CustomFields\Services\CustomFieldValidationService;
use App\Modules\Tenancy\Context\TenantContext;
use Closure;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class CustomFieldManager extends Component
{
    use AuthorizesRequests;

    public string $entityType = 'lead';

    public string $fieldKey = '';

    public string $label = '';

    public string $dataType = 'text';

    public string $optionsInput = '';

    /** @var array<int, string> */
    public array $validationRules = [];

    public bool $isRequired = false;

    public int $orderIndex = 0;

    public ?string $statusMessage = null;

    public function setEntityType(string $entityType): void
    {
        if (in_array($entityType, CustomFieldDefinition::ENTITY_TYPES, true)) {
            $this->entityType = $entityType;
            $this->statusMessage = null;
        }
    }

    public function createField(TenantContext $tenantContext): void
    {
        $this->authorize('create', CustomFieldDefinition::class);

        $tenant = $tenantContext->getTenant();
        $this->fieldKey = Str::lower(trim($this->fieldKey));

        $options = [];
        if (in_array($this->dataType, ['select', 'multiselect'], true)) {
            $options = array_values(array_filter(array_map(
                static fn (string $opt): string => trim($opt),
                explode(',', $this->optionsInput),
            ), static fn (string $opt): bool => $opt !== ''));
        }

        $this->validate([
            'entityType' => ['required', Rule::in(CustomFieldDefinition::ENTITY_TYPES)],
            'fieldKey' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('custom_field_definitions', 'field_key')
                    ->where('organization_id', $tenant?->id)
                    ->where('entity_type', $this->entityType),
            ],
            'label' => ['required', 'string', 'max:100'],
            'dataType' => ['required', Rule::in(CustomFieldDefinition::DATA_TYPES)],
            'validationRules' => ['nullable', 'array'],
            'validationRules.*' => [
                'string',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $trimmed = trim($value);
                    if (! str_starts_with($trimmed, 'regex:')) {
                        return;
                    }

                    if (! CustomFieldValidationService::isValidRegexPattern(substr($trimmed, 6))) {
                        $fail("The {$attribute} rule contains an invalid regex pattern.");
                    }
                },
            ],
            'isRequired' => ['boolean'],
            'orderIndex' => ['integer', 'min:0', 'max:65535'],
        ]);

        if (in_array($this->dataType, ['select', 'multiselect'], true) && count($options) === 0) {
            $this->addError('optionsInput', 'At least one comma-separated option is required for select fields.');

            return;
        }

        CustomFieldDefinition::create([
            'organization_id' => $tenant->id,
            'entity_type' => $this->entityType,
            'field_key' => $this->fieldKey,
            'label' => $this->label,
            'data_type' => $this->dataType,
            'options' => in_array($this->dataType, ['select', 'multiselect'], true) ? $options : null,
            'validation_rules' => $this->validationRules !== [] ? array_values($this->validationRules) : null,
            'is_required' => $this->isRequired,
            'order_index' => $this->orderIndex,
        ]);

        $this->reset(['fieldKey', 'label', 'optionsInput', 'validationRules', 'isRequired', 'orderIndex']);
        $this->dataType = 'text';
        $this->statusMessage = 'Custom field definition created.';
    }

    public function deleteField(int $definitionId): void
    {
        $definition = CustomFieldDefinition::query()->findOrFail($definitionId);
        $this->authorize('delete', $definition);

        $definition->delete();
        $this->statusMessage = 'Custom field definition deleted.';
    }

    public function render(): View
    {
        $definitions = CustomFieldDefinition::query()
            ->where('entity_type', $this->entityType)
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();

        return view('livewire.custom-fields.custom-field-manager', [
            'definitions' => $definitions,
            'entityTypes' => CustomFieldDefinition::ENTITY_TYPES,
            'dataTypes' => CustomFieldDefinition::DATA_TYPES,
        ]);
    }
}
