<?php

namespace App\Modules\Tracking\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LinkClickedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $trackingLinkId,
        public readonly int $linkClickId,
        public readonly string $clickToken,
    ) {}
}
