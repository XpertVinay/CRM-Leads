<?php

namespace App\Modules\CustomFields\Services;

use App\Modules\CustomFields\Models\CustomFieldDefinition;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class CustomFieldValidationService
{
    private const ALLOWED_EXACT_RULES = [
        'integer',
        'alpha',
        'alpha_num',
        'alpha_dash',
        'email',
        'url',
        'uuid',
    ];

    private const ALLOWED_PREFIX_RULES = [
        'min:',
        'max:',
        'between:',
        'digits:',
        'digits_between:',
        'size:',
        'regex:',
        'before:',
        'after:',
        'starts_with:',
        'ends_with:',
        'date_format:',
    ];

    public function __construct(
        private readonly CustomFieldRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validate(string $entityType, array $payload, ?int $organizationId = null): array
    {
        if (! in_array($entityType, CustomFieldDefinition::ENTITY_TYPES, true)) {
            throw new InvalidArgumentException("Unsupported custom field entity type [{$entityType}].");
        }

        $definitions = $this->registry->forEntity($entityType, $organizationId);

        if ($definitions->isEmpty()) {
            return [];
        }

        $normalizedInput = [];
        $rules = [];
        $attributes = [];

        foreach ($definitions as $definition) {
            $key = $definition->field_key;
            $attributes[$key] = $definition->label;

            foreach ($this->rulesFor($definition) as $ruleKey => $ruleList) {
                $rules[$ruleKey] = $ruleList;
            }

            if (array_key_exists($key, $payload)) {
                $normalizedInput[$key] = $this->normalizeValue($definition, $payload[$key]);
            }
        }

        Validator::make($normalizedInput, $rules, [], $attributes)->validate();

        $validated = [];
        foreach ($definitions as $definition) {
            $key = $definition->field_key;
            if (! array_key_exists($key, $normalizedInput)) {
                continue;
            }

            $validated[$key] = $this->coerceValue($definition, $normalizedInput[$key]);
        }

        return $validated;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rulesFor(CustomFieldDefinition $definition): array
    {
        $key = $definition->field_key;
        $basePrefix = $definition->is_required ? 'required' : 'nullable';
        $safeExtra = $this->filterSafeRules($definition->validation_rules ?? []);
        $options = $definition->normalizedOptions();

        return match ($definition->data_type) {
            'text' => [
                $key => array_merge([$basePrefix, 'string', 'max:255'], $safeExtra),
            ],
            'textarea' => [
                $key => array_merge([$basePrefix, 'string', 'max:10000'], $safeExtra),
            ],
            'number' => [
                $key => array_merge([$basePrefix, 'numeric'], $safeExtra),
            ],
            'date' => [
                $key => array_merge([$basePrefix, 'date'], $safeExtra),
            ],
            'boolean' => [
                $key => [$basePrefix, 'boolean'],
            ],
            'select' => [
                $key => [$basePrefix, 'string', Rule::in($options)],
            ],
            'multiselect' => [
                $key => $definition->is_required
                    ? ['required', 'array', 'min:1']
                    : ['nullable', 'array'],
                "{$key}.*" => ['required', 'string', Rule::in($options)],
            ],
            default => throw new InvalidArgumentException("Unsupported custom field data type [{$definition->data_type}]."),
        };
    }

    private function normalizeValue(CustomFieldDefinition $definition, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($definition->data_type === 'multiselect') {
            if ($value === '') {
                return null;
            }

            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return null;
            }

            if ($definition->data_type === 'boolean') {
                $lower = strtolower($trimmed);
                if (in_array($lower, ['true', 'yes', 'on', '1'], true)) {
                    return true;
                }
                if (in_array($lower, ['false', 'no', 'off', '0'], true)) {
                    return false;
                }

                return $value;
            }
        }

        if ($definition->data_type === 'boolean' && ($value === 1 || $value === 0 || is_bool($value))) {
            return (bool) $value;
        }

        return $value;
    }

    private function coerceValue(CustomFieldDefinition $definition, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($definition->data_type) {
            'text', 'textarea', 'select' => (string) $value,
            'number' => str_contains((string) $value, '.') ? (float) $value : (int) $value,
            'date' => Carbon::parse((string) $value)->toDateString(),
            'boolean' => (bool) $value,
            'multiselect' => array_values(array_unique(array_map('strval', (array) $value))),
            default => $value,
        };
    }

    /**
     * @param  array<int, mixed>  $rawRules
     * @return array<int, string>
     */
    private function filterSafeRules(array $rawRules): array
    {
        $safe = [];

        foreach ($rawRules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            $rule = trim($rule);
            if ($rule === '') {
                continue;
            }

            if (in_array($rule, self::ALLOWED_EXACT_RULES, true)) {
                $safe[] = $rule;

                continue;
            }

            foreach (self::ALLOWED_PREFIX_RULES as $prefix) {
                if (str_starts_with($rule, $prefix)) {
                    if ($prefix === 'regex:' && ! self::isValidRegexPattern(substr($rule, 6))) {
                        break;
                    }

                    $safe[] = $rule;
                    break;
                }
            }
        }

        return $safe;
    }

    public static function isValidRegexPattern(string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        return @preg_match($pattern, '') !== false;
    }
}
