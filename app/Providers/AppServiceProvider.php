<?php

namespace App\Providers;

use App\Models\Organization;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\FollowUps\Models\FollowUp;
use App\Modules\Analytics\Livewire\AttributionReportView;
use App\Modules\Analytics\Livewire\FunnelVelocityDashboard;
use App\Modules\Analytics\Services\DashboardMetricsService;
use App\Modules\Analytics\Services\FunnelMetricsCalculator;
use App\Modules\Analytics\Services\ProductivityMetricsService;
use App\Modules\Analytics\Services\ROASAggregator;
use App\Modules\Analytics\Services\WhatsAppHealthService;
use App\Modules\Campaigns\Services\CampaignMetricsService;
use App\Modules\Communications\WhatsApp\Adapters\MetaGraphApiClient;
use App\Modules\Compliance\Services\AuditService;
use App\Modules\Compliance\Services\GdprComplianceService;
use App\Modules\Communications\WhatsApp\Livewire\TemplateSelector;
use App\Modules\Communications\WhatsApp\Livewire\WhatsAppChatWidget;
use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Communications\WhatsApp\Services\CoexistenceService;
use App\Modules\Communications\WhatsApp\Services\ConsentService;
use App\Modules\Communications\WhatsApp\Services\WindowEnforcementService;
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
use App\Modules\Pipeline\Livewire\AccountList;
use App\Modules\Pipeline\Livewire\ContactCard;
use App\Modules\Pipeline\Livewire\DealKanbanBoard;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Pipeline\Models\Pipeline;
use App\Modules\Pipeline\Services\ActivityTimelineService;
use App\Modules\Pipeline\Services\DealStageTransitionService;
use App\Modules\Pipeline\Services\StaleDealSlaService;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Livewire\OrganizationSwitcher;
use App\Modules\Tenancy\Middleware\AuthorizeTenantRole;
use App\Modules\Tenancy\Middleware\EnsureTenantContext;
use App\Modules\Tenancy\Services\TenantProvisioningService;
use App\Modules\Tracking\Livewire\TrackingLinkManager;
use App\Modules\Tracking\Models\TrackingLink;
use App\Modules\Tracking\Services\AttributionCalculationService;
use App\Modules\Tracking\Services\LinkShortenerService;
use App\Policies\AccountPolicy;
use App\Policies\CampaignPolicy;
use App\Policies\ContactPolicy;
use App\Policies\CustomFieldDefinitionPolicy;
use App\Policies\DealPolicy;
use App\Policies\FollowUpPolicy;
use App\Policies\LeadPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PipelinePolicy;
use App\Policies\TrackingLinkPolicy;
use App\Policies\WhatsAppPolicy;
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
        $this->app->singleton(ActivityTimelineService::class);
        $this->app->singleton(DealStageTransitionService::class);
        $this->app->singleton(StaleDealSlaService::class);
        $this->app->singleton(MetaGraphApiClient::class);
        $this->app->singleton(WindowEnforcementService::class);
        $this->app->singleton(ConsentService::class);
        $this->app->singleton(CoexistenceService::class);
        $this->app->singleton(AuditService::class);
        $this->app->singleton(GdprComplianceService::class);
        $this->app->singleton(FunnelMetricsCalculator::class);
        $this->app->singleton(ROASAggregator::class);
        $this->app->singleton(ProductivityMetricsService::class);
        $this->app->singleton(WhatsAppHealthService::class);
        $this->app->singleton(CampaignMetricsService::class);
        $this->app->singleton(DashboardMetricsService::class);
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
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(FollowUp::class, FollowUpPolicy::class);
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Deal::class, DealPolicy::class);
        Gate::policy(Pipeline::class, PipelinePolicy::class);
        Gate::policy(WhatsAppAccount::class, WhatsAppPolicy::class);
        Gate::policy(WhatsAppConversation::class, WhatsAppPolicy::class);

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
            Livewire::component('pipeline.deal-kanban-board', DealKanbanBoard::class);
            Livewire::component('pipeline.account-list', AccountList::class);
            Livewire::component('pipeline.contact-card', ContactCard::class);
            Livewire::component('whatsapp.chat-widget', WhatsAppChatWidget::class);
            Livewire::component('whatsapp.template-selector', TemplateSelector::class);
            Livewire::component('analytics.funnel-velocity-dashboard', FunnelVelocityDashboard::class);
            Livewire::component('analytics.attribution-report-view', AttributionReportView::class);
        }
    }
}
