<?php

namespace App\Modules\Tenancy\Services;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Events\OrganizationProvisionedEvent;
use App\Modules\Tenancy\Support\OrganizationInputNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function provisionOrganization(User $owner, array $attributes): Organization
    {
        $organization = DB::transaction(function () use ($owner, $attributes): Organization {
            $name = trim((string) ($attributes['name'] ?? 'Default Organization'));
            $rawSlug = OrganizationInputNormalizer::normalizeNullableString($attributes['slug'] ?? null);
            $slug = is_string($rawSlug) ? Str::slug($rawSlug) : null;
            if ($slug === '') {
                $slug = null;
            }

            $registrationIdentifier = OrganizationInputNormalizer::normalizeNullableString(
                $attributes['registration_identifier'] ?? null,
            );
            $primaryContactEmail = OrganizationInputNormalizer::normalizeNullableEmail(
                $attributes['primary_contact_email'] ?? null,
            );
            $timezone = OrganizationInputNormalizer::normalizeNullableString($attributes['timezone'] ?? null) ?? 'UTC';
            $rawCurrency = OrganizationInputNormalizer::normalizeNullableString($attributes['currency'] ?? null) ?? 'USD';
            $currency = strtoupper((string) $rawCurrency);

            $organization = Organization::create([
                'name' => $name,
                'slug' => $slug,
                'registration_identifier' => $registrationIdentifier,
                'primary_contact_email' => $primaryContactEmail,
                'status' => 'active',
                'timezone' => $timezone,
                'currency' => $currency,
                'settings' => $attributes['settings'] ?? [],
            ]);

            $organization->users()->attach($owner->id, [
                'role' => TenantRole::OrgAdmin->value,
            ]);

            $this->seedDefaultPipelineIfAvailable($organization);

            return $organization;
        });

        OrganizationProvisionedEvent::dispatch($organization, $owner);

        return $organization;
    }

    public function seedDefaultPipelineIfAvailable(Organization $organization): void
    {
        if (! Schema::hasTable('pipelines') || ! Schema::hasTable('pipeline_stages')) {
            return;
        }

        $now = now();

        $pipelineId = DB::table('pipelines')->insertGetId([
            'organization_id' => $organization->id,
            'name' => 'Default Sales Pipeline',
            'is_default' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $stages = [
            ['name' => 'New', 'order_index' => 1, 'win_probability' => 10, 'category' => 'open', 'stale_days' => 14],
            ['name' => 'Qualified', 'order_index' => 2, 'win_probability' => 30, 'category' => 'open', 'stale_days' => 14],
            ['name' => 'Proposal', 'order_index' => 3, 'win_probability' => 60, 'category' => 'open', 'stale_days' => 14],
            ['name' => 'Won', 'order_index' => 4, 'win_probability' => 100, 'category' => 'won', 'stale_days' => null],
            ['name' => 'Lost', 'order_index' => 5, 'win_probability' => 0, 'category' => 'lost', 'stale_days' => null],
        ];

        foreach ($stages as $stage) {
            DB::table('pipeline_stages')->insert([
                'pipeline_id' => $pipelineId,
                'name' => $stage['name'],
                'order_index' => $stage['order_index'],
                'win_probability' => $stage['win_probability'],
                'category' => $stage['category'],
                'stale_days' => $stage['stale_days'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
