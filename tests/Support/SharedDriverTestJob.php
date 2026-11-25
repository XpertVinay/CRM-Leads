<?php

namespace Tests\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SharedDriverTestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $markerKey,
        public readonly string $markerValue,
    ) {}

    public function handle(): void
    {
        Cache::store('database')->put($this->markerKey, $this->markerValue, 60);
    }
}
