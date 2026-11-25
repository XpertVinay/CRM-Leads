<?php

namespace App\Modules\Leads\DTOs;

readonly class LeadConversionData
{
    public function __construct(
        public ?int $accountId = null,
        public ?string $accountName = null,
        public ?string $accountDomain = null,
        public ?string $accountIndustry = null,
        public bool $createDeal = false,
        public ?string $dealTitle = null,
        public float $dealAmount = 0.0,
        public ?string $dealCurrency = null,
        public ?int $pipelineId = null,
        public ?int $pipelineStageId = null,
        public ?string $expectedCloseDate = null,
        public ?int $assignedUserId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accountId: isset($data['account_id']) && $data['account_id'] !== '' ? (int) $data['account_id'] : null,
            accountName: self::nullableString($data['account_name'] ?? null),
            accountDomain: self::nullableString($data['account_domain'] ?? null),
            accountIndustry: self::nullableString($data['account_industry'] ?? null),
            createDeal: filter_var($data['create_deal'] ?? false, FILTER_VALIDATE_BOOLEAN),
            dealTitle: self::nullableString($data['deal_title'] ?? null),
            dealAmount: isset($data['deal_amount']) && $data['deal_amount'] !== '' ? (float) $data['deal_amount'] : 0.0,
            dealCurrency: self::nullableString($data['deal_currency'] ?? null),
            pipelineId: isset($data['pipeline_id']) && $data['pipeline_id'] !== '' ? (int) $data['pipeline_id'] : null,
            pipelineStageId: isset($data['pipeline_stage_id']) && $data['pipeline_stage_id'] !== '' ? (int) $data['pipeline_stage_id'] : null,
            expectedCloseDate: self::nullableString($data['expected_close_date'] ?? null),
            assignedUserId: isset($data['assigned_user_id']) && $data['assigned_user_id'] !== '' ? (int) $data['assigned_user_id'] : null,
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
