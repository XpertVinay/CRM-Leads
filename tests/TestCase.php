<?php

namespace Tests;

use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        if ($this->app && $this->app->bound(TenantContext::class)) {
            $this->app->make(TenantContext::class)->clear();
        }

        parent::tearDown();
    }
}
