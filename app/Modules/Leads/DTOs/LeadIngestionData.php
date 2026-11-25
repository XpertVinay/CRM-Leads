<?php

namespace App\Modules\Leads\DTOs;

readonly class LeadIngestionData
{
    /**
     * @param  array<string, mixed>  $customFields
     */
    public function __construct(
        public string $type,
        public string $firstName,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $companyName = null,
        public ?string $jobTitle = null,
        public ?int $employeeCount = null,
        public ?string $location = null,
        public string $source = 'direct',
        public string $status = 'new',
        public ?int $assignedUserId = null,
        public array $customFields = [],
        public ?string $clickToken = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: (string) ($data['type'] ?? 'B2C'),
            firstName: trim((string) ($data['first_name'] ?? '')),
            lastName: self::nullableString($data['last_name'] ?? null),
            email: self::nullableString($data['email'] ?? null),
            phone: self::nullableString($data['phone'] ?? null),
            companyName: self::nullableString($data['company_name'] ?? null),
            jobTitle: self::nullableString($data['job_title'] ?? null),
            employeeCount: isset($data['employee_count']) && $data['employee_count'] !== ''
                ? (int) $data['employee_count']
                : null,
            location: self::nullableString($data['location'] ?? null),
            source: self::nullableString($data['source'] ?? null) ?? 'direct',
            status: self::nullableString($data['status'] ?? null) ?? 'new',
            assignedUserId: isset($data['assigned_user_id']) && $data['assigned_user_id'] !== ''
                ? (int) $data['assigned_user_id']
                : null,
            customFields: is_array($data['custom_fields'] ?? null) ? $data['custom_fields'] : [],
            clickToken: self::nullableString($data['click_token'] ?? null),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
