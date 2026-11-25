<?php

namespace App\Modules\Tenancy\Scopes;

use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantContext = app(TenantContext::class);

        if ($tenantContext->hasTenant()) {
            $builder->where(
                $model->getTable().'.organization_id',
                $tenantContext->getTenantId(),
            );
        }
    }
}
