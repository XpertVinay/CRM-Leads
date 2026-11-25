<?php

namespace Tests\Feature;

use App\Modules\Tenancy\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;
    use WithTenancyHelpers;

    public function test_guest_sees_trust_offers_subscription_plans_and_registration_ctas(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertDontSee('/build/assets/')
            ->assertSee('assets/css/landing.css')
            ->assertSee('assets/js/landing.js')
            ->assertSee(route('register'), false)
            ->assertSee(route('login'), false)
            ->assertSee('Starter Plan')
            ->assertSee('2,499/-')
            ->assertSee('Gold Plan')
            ->assertSee('4,999/-')
            ->assertSee('Org Admin')
            ->assertSee('Email, SMS, WhatsApp');
    }

    public function test_authenticated_user_sees_dashboard_cta_on_landing_page(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->actingAsOrgMember($admin, $org);

        $this->get('/')
            ->assertOk()
            ->assertSee(url('/dashboard'), false)
            ->assertSee(route('organizations.index'), false);
    }
}
