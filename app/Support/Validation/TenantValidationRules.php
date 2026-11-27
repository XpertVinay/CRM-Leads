<?php

namespace App\Support\Validation;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class TenantValidationRules
{
    /**
     * Validate that a foreign key exists within the given organization.
     */
    public static function existsInOrganization(string $table, int $organizationId, bool $softDeletes = true): Exists
    {
        $rule = Rule::exists($table, 'id')->where('organization_id', $organizationId);

        if ($softDeletes) {
            $rule->whereNull('deleted_at');
        }

        return $rule;
    }

    /**
     * Validate that a user_id is a member of the given organization.
     */
    public static function organizationMemberExists(int $organizationId): Exists
    {
        return Rule::exists('organization_user', 'user_id')->where('organization_id', $organizationId);
    }
}
