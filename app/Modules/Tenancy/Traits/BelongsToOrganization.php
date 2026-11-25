<?php

namespace App\Modules\Tenancy\Traits;

use App\Models\Organization;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model): void {
            if (empty($model->organization_id) && app(TenantContext::class)->hasTenant()) {
                $model->organization_id = app(TenantContext::class)->getTenantId();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
