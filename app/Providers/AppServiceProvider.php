<?php

namespace App\Providers;

use App\Models\Organization;
use App\Modules\CustomFields\Livewire\CustomFieldManager;
use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\CustomFields\Services\CustomFieldRegistry;
use App\Modules\CustomFields\Services\CustomFieldValidationService;
use App\Modules\Leads\Livewire\ConvertLeadWizard;
use App\Modules\Leads\Livewire\LeadDetailModal;
use App\Modules\Leads\Livewire\LeadInboxTable;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Services\LeadAssignmentService;
use App\Modules\Leads\Services\LeadConversionService;
use App\Modules\Leads\Services\LeadIngestionService;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Livewire\OrganizationSwitcher;
use App\Modules\Tenancy\Middleware\AuthorizeTenantRole;
use App\Modules\Tenancy\Middleware\EnsureTenantContext;
use App\Modules\Tenancy\Services\TenantProvisioningService;
use App\Modules\Tracking\Livewire\TrackingLinkManager;
use App\Modules\Tracking\Models\TrackingLink;
use App\Modules\Tracking\Services\AttributionCalculationService;
use App\Modules\Tracking\Services\LinkShortenerService;
use App\Policies\CustomFieldDefinitionPolicy;
use App\Policies\LeadPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\TrackingLinkPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(TenantProvisioningService::class);
        $this->app->singleton(CustomFieldRegistry::class);
        $this->app->singleton(CustomFieldValidationService::class);
        $this->app->singleton(LinkShortenerService::class);
        $this->app->singleton(AttributionCalculationService::class);
        $this->app->singleton(LeadAssignmentService::class);
        $this->app->singleton(LeadIngestionService::class);
        $this->app->singleton(LeadConversionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(CustomFieldDefinition::class, CustomFieldDefinitionPolicy::class);
        Gate::policy(TrackingLink::class, TrackingLinkPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);

        if (class_exists(Livewire::class)) {
            Livewire::addPersistentMiddleware([
                EnsureTenantContext::class,
                AuthorizeTenantRole::class,
            ]);

            Livewire::component('tenancy.organization-switcher', OrganizationSwitcher::class);
            Livewire::component('custom-fields.custom-field-manager', CustomFieldManager::class);
            Livewire::component('tracking.tracking-link-manager', TrackingLinkManager::class);
            Livewire::component('leads.lead-inbox-table', LeadInboxTable::class);
            Livewire::component('leads.lead-detail-modal', LeadDetailModal::class);
            Livewire::component('leads.convert-lead-wizard', ConvertLeadWizard::class);
        }
    }
}
