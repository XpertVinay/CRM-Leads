<?php

namespace App\Modules\Tenancy\Events;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrganizationProvisionedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Organization $organization,
        public readonly User $owner,
    ) {}
}
