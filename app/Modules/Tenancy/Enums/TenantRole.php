<?php

namespace App\Modules\Tenancy\Enums;

enum TenantRole: string
{
    case OrgAdmin = 'org_admin';
    case SalesManager = 'sales_manager';
    case SalesRep = 'sales_rep';
    case ReadOnly = 'read_only';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::OrgAdmin => 'Organization Admin',
            self::SalesManager => 'Manager',
            self::SalesRep => 'Sales Executive',
            self::ReadOnly => 'Viewer',
        };
    }
}
