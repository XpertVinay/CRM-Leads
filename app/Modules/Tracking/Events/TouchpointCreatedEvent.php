<?php

namespace App\Modules\Tracking\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TouchpointCreatedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $touchpointId,
        public readonly string $eventMilestone,
    ) {}
}
