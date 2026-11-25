<?php

namespace App\Modules\Tenancy\Context;

use App\Models\Organization;

class TenantContext
{
    protected ?Organization $organization = null;

    public function setTenant(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function getTenant(): ?Organization
    {
        return $this->organization;
    }

    public function getTenantId(): ?int
    {
        return $this->organization?->id;
    }

    public function hasTenant(): bool
    {
        return $this->organization !== null;
    }

    public function clear(): void
    {
        $this->organization = null;
    }
}
