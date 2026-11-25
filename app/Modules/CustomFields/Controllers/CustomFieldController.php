<?php

namespace App\Modules\CustomFields\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\CustomFields\Services\CustomFieldValidationService;
use App\Modules\Tenancy\Context\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomFieldController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageSettings', CustomFieldDefinition::class);

        return view('settings.custom-fields', [
            'entityTypes' => CustomFieldDefinition::ENTITY_TYPES,
            'dataTypes' => CustomFieldDefinition::DATA_TYPES,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CustomFieldDefinition::class);

        $query = CustomFieldDefinition::query()
            ->orderBy('entity_type')
            ->orderBy('order_index')
            ->orderBy('id');

        $entityType = $request->query('entity_type');
        if (is_string($entityType) && $entityType !== '') {
            if (! in_array($entityType, CustomFieldDefinition::ENTITY_TYPES, true)) {
                return response()->json(['message' => 'Invalid entity_type filter.'], 422);
            }
            $query->where('entity_type', $entityType);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request, TenantContext $tenantContext): JsonResponse
    {
        Gate::authorize('create', CustomFieldDefinition::class);

        $tenant = $tenantContext->getTenant();
        $normalizedKey = Str::lower(trim((string) $request->input('field_key', '')));
        $request->merge(['field_key' => $normalizedKey]);

        $requiresOptions = in_array($request->input('data_type'), ['select', 'multiselect'], true);

        $validated = $request->validate([
            'entity_type' => ['required', 'string', Rule::in(CustomFieldDefinition::ENTITY_TYPES)],
            'field_key' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('custom_field_definitions', 'field_key')
                    ->where('organization_id', $tenant->id)
                    ->where('entity_type', (string) $request->input('entity_type')),
            ],
            'label' => ['required', 'string', 'max:100'],
            'data_type' => ['required', 'string', Rule::in(CustomFieldDefinition::DATA_TYPES)],
            'options' => $requiresOptions
                ? ['required', 'array', 'min:1']
                : ['nullable', 'array'],
            'options.*' => ['required', 'string', 'min:1', 'max:100'],
            'validation_rules' => ['nullable', 'array'],
            'validation_rules.*' => ['string', 'max:100', $this->regexValidationRule()],
            'is_required' => ['sometimes', 'boolean'],
            'order_index' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ]);

        $definition = CustomFieldDefinition::create([
            'organization_id' => $tenant->id,
            'entity_type' => $validated['entity_type'],
            'field_key' => $validated['field_key'],
            'label' => $validated['label'],
            'data_type' => $validated['data_type'],
            'options' => $requiresOptions
                ? $this->normalizeOptions($validated['options'] ?? [])
                : null,
            'validation_rules' => $validated['validation_rules'] ?? null,
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'order_index' => (int) ($validated['order_index'] ?? 0),
        ]);

        return response()->json([
            'message' => 'Custom field definition created.',
            'data' => $definition,
        ], 201);
    }

    public function update(Request $request, CustomFieldDefinition $definition): JsonResponse
    {
        Gate::authorize('update', $definition);

        $requiresOptions = in_array($definition->data_type, ['select', 'multiselect'], true);
        $optionsProvided = $request->exists('options');

        $optionsRules = $requiresOptions
            ? ($optionsProvided ? ['required', 'array', 'min:1'] : ['sometimes', 'array', 'min:1'])
            : ['nullable', 'array'];

        $validated = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:100'],
            'options' => $optionsRules,
            'options.*' => ['required', 'string', 'min:1', 'max:100'],
            'validation_rules' => ['nullable', 'array'],
            'validation_rules.*' => ['string', 'max:100', $this->regexValidationRule()],
            'is_required' => ['sometimes', 'boolean'],
            'order_index' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ]);

        $definition->update([
            'label' => $validated['label'] ?? $definition->label,
            'options' => array_key_exists('options', $validated)
                ? ($requiresOptions ? $this->normalizeOptions($validated['options']) : null)
                : $definition->options,
            'validation_rules' => array_key_exists('validation_rules', $validated)
                ? $validated['validation_rules']
                : $definition->validation_rules,
            'is_required' => array_key_exists('is_required', $validated)
                ? (bool) $validated['is_required']
                : $definition->is_required,
            'order_index' => array_key_exists('order_index', $validated)
                ? (int) $validated['order_index']
                : $definition->order_index,
        ]);

        return response()->json([
            'message' => 'Custom field definition updated.',
            'data' => $definition->fresh(),
        ]);
    }

    public function destroy(CustomFieldDefinition $definition): JsonResponse
    {
        Gate::authorize('delete', $definition);

        $definition->delete();

        return response()->json([
            'message' => 'Custom field definition deleted.',
        ]);
    }

    /**
     * @param  array<int, string>  $options
     * @return array<int, string>
     */
    private function normalizeOptions(array $options): array
    {
        return array_values(array_unique(array_filter(
            array_map('trim', $options),
            static fn (string $option): bool => $option !== '',
        )));
    }

    private function regexValidationRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $trimmed = trim($value);
            if (! str_starts_with($trimmed, 'regex:')) {
                return;
            }

            $pattern = substr($trimmed, 6);
            if (! CustomFieldValidationService::isValidRegexPattern($pattern)) {
                $fail("The {$attribute} rule contains an invalid regex pattern.");
            }
        };
    }
}
