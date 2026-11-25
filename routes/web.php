<?php

use App\Modules\Analytics\Controllers\AnalyticsDashboardController;
use App\Modules\Analytics\Controllers\ReportController;
use App\Modules\Campaigns\Controllers\CampaignController;
use App\Modules\Communications\WhatsApp\Controllers\WhatsAppChatController;
use App\Modules\Communications\WhatsApp\Controllers\WhatsAppSettingsController;
use App\Modules\Compliance\Controllers\GdprController;
use App\Modules\CustomFields\Controllers\CustomFieldController;
use App\Modules\FollowUps\Controllers\FollowUpController;
use App\Modules\Leads\Controllers\LeadController;
use App\Modules\Notifications\Controllers\NotificationController;
use App\Modules\Pipeline\Controllers\AccountController;
use App\Modules\Pipeline\Controllers\ActivityController;
use App\Modules\Pipeline\Controllers\ContactController;
use App\Modules\Pipeline\Controllers\DealController;
use App\Modules\Pipeline\Controllers\PipelineController;
use App\Modules\Tenancy\Controllers\ApiTokenController;
use App\Modules\Tenancy\Controllers\AuthController;
use App\Modules\Tenancy\Controllers\DashboardController;
use App\Modules\Tenancy\Controllers\GlobalSearchController;
use App\Modules\Tenancy\Controllers\InvitationAcceptanceController;
use App\Modules\Tenancy\Controllers\OrganizationController;
use App\Modules\Tenancy\Controllers\OrganizationSettingsController;
use App\Modules\Tenancy\Controllers\TeamMemberController;
use App\Modules\Tracking\Controllers\PublicLeadCaptureController;
use App\Modules\Tracking\Controllers\RedirectTrackingLinkController;
use App\Modules\Tracking\Controllers\TrackingLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/l/{code}', RedirectTrackingLinkController::class)
    ->middleware('throttle:120,1')
    ->name('tracking-links.redirect');

Route::get('/go/{slug}', [PublicLeadCaptureController::class, 'show'])
    ->middleware('throttle:120,1')
    ->name('tracking-links.capture');
Route::post('/go/{slug}', [PublicLeadCaptureController::class, 'submit'])
    ->middleware('throttle:60,1')
    ->name('tracking-links.capture.submit');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::get('/invitations/{token}/accept', [InvitationAcceptanceController::class, 'show'])
    ->name('invitations.show');
Route::post('/invitations/{token}/accept', [InvitationAcceptanceController::class, 'accept'])
    ->name('invitations.accept');

Route::middleware(['auth'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::post('/ajax/organizations/switch', [OrganizationController::class, 'switch'])->name('ajax.organizations.switch');
});

Route::middleware(['auth', 'tenant'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/ajax/dashboard/metrics', [DashboardController::class, 'metricsJson'])->name('ajax.dashboard.metrics');
    Route::get('/ajax/search', GlobalSearchController::class)->name('ajax.search');

    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::get('/ajax/campaigns', [CampaignController::class, 'list'])->name('ajax.campaigns.index');
    Route::post('/ajax/campaigns', [CampaignController::class, 'store'])->name('ajax.campaigns.store');
    Route::match(['put', 'patch'], '/ajax/campaigns/{campaign}', [CampaignController::class, 'update'])->name('ajax.campaigns.update');
    Route::delete('/ajax/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('ajax.campaigns.destroy');

    Route::get('/follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index');
    Route::get('/ajax/follow-ups', [FollowUpController::class, 'list'])->name('ajax.follow-ups.index');
    Route::post('/ajax/follow-ups', [FollowUpController::class, 'store'])->name('ajax.follow-ups.store');
    Route::match(['put', 'patch'], '/ajax/follow-ups/{followUp}', [FollowUpController::class, 'update'])->name('ajax.follow-ups.update');
    Route::delete('/ajax/follow-ups/{followUp}', [FollowUpController::class, 'destroy'])->name('ajax.follow-ups.destroy');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/ajax/reports', [ReportController::class, 'data'])->name('ajax.reports.data');
    Route::get('/reports/export', [ReportController::class, 'exportCsv'])->name('reports.export');

    Route::get('/tracking-links', [TrackingLinkController::class, 'index'])->name('tracking-links.index');
    Route::get('/ajax/tracking-links', [TrackingLinkController::class, 'list'])->name('ajax.tracking-links.index');
    Route::post('/ajax/tracking-links', [TrackingLinkController::class, 'store'])->name('ajax.tracking-links.store');
    Route::match(['put', 'patch'], '/ajax/tracking-links/{trackingLink}', [TrackingLinkController::class, 'update'])->name('ajax.tracking-links.update');
    Route::delete('/ajax/tracking-links/{trackingLink}', [TrackingLinkController::class, 'destroy'])->name('ajax.tracking-links.destroy');
    Route::get('/ajax/attribution/summary', [TrackingLinkController::class, 'attributionSummary'])->name('ajax.attribution.summary');

    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/export', [LeadController::class, 'exportCsv'])->name('leads.export');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::get('/ajax/leads', [LeadController::class, 'list'])->name('ajax.leads.index');
    Route::post('/ajax/leads', [LeadController::class, 'store'])->name('ajax.leads.store');
    Route::post('/ajax/leads/bulk', [LeadController::class, 'bulkAction'])->name('ajax.leads.bulk');
    Route::get('/ajax/leads/{lead}', [LeadController::class, 'showJson'])->name('ajax.leads.show');
    Route::match(['put', 'patch'], '/ajax/leads/{lead}', [LeadController::class, 'update'])->name('ajax.leads.update');
    Route::delete('/ajax/leads/{lead}', [LeadController::class, 'destroy'])->name('ajax.leads.destroy');
    Route::patch('/ajax/leads/{lead}/assign', [LeadController::class, 'assign'])->name('ajax.leads.assign');
    Route::post('/ajax/leads/{lead}/convert', [LeadController::class, 'convert'])->name('ajax.leads.convert');

    Route::get('/ajax/custom-fields', [CustomFieldController::class, 'list'])->name('ajax.custom-fields.list');

    // Phase 3: Accounts, Contacts, Deals, Pipelines & Activities
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/{account}', [AccountController::class, 'show'])->name('accounts.show');
    Route::get('/ajax/accounts', [AccountController::class, 'list'])->name('ajax.accounts.index');
    Route::post('/ajax/accounts', [AccountController::class, 'store'])->name('ajax.accounts.store');
    Route::get('/ajax/accounts/{account}', [AccountController::class, 'showJson'])->name('ajax.accounts.show');
    Route::match(['put', 'patch'], '/ajax/accounts/{account}', [AccountController::class, 'update'])->name('ajax.accounts.update');
    Route::delete('/ajax/accounts/{account}', [AccountController::class, 'destroy'])->name('ajax.accounts.destroy');

    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::get('/ajax/contacts', [ContactController::class, 'list'])->name('ajax.contacts.index');
    Route::post('/ajax/contacts', [ContactController::class, 'store'])->name('ajax.contacts.store');
    Route::get('/ajax/contacts/{contact}', [ContactController::class, 'showJson'])->name('ajax.contacts.show');
    Route::match(['put', 'patch'], '/ajax/contacts/{contact}', [ContactController::class, 'update'])->name('ajax.contacts.update');
    Route::delete('/ajax/contacts/{contact}', [ContactController::class, 'destroy'])->name('ajax.contacts.destroy');

    Route::get('/deals', [DealController::class, 'index'])->name('deals.index');
    Route::get('/deals/{deal}', [DealController::class, 'show'])->name('deals.show');
    Route::get('/ajax/deals', [DealController::class, 'list'])->name('ajax.deals.index');
    Route::post('/ajax/deals', [DealController::class, 'store'])->name('ajax.deals.store');
    Route::get('/ajax/deals/{deal}', [DealController::class, 'showJson'])->name('ajax.deals.show');
    Route::match(['put', 'patch'], '/ajax/deals/{deal}', [DealController::class, 'update'])->name('ajax.deals.update');
    Route::delete('/ajax/deals/{deal}', [DealController::class, 'destroy'])->name('ajax.deals.destroy');
    Route::patch('/ajax/deals/{deal}/stage', [DealController::class, 'transitionStage'])->name('ajax.deals.stage');

    Route::get('/ajax/activities', [ActivityController::class, 'list'])->name('ajax.activities.index');
    Route::post('/ajax/activities', [ActivityController::class, 'store'])->name('ajax.activities.store');

    // Phase 4: WhatsApp Cloud API & Coexistence
    Route::get('/whatsapp/chat', [WhatsAppChatController::class, 'index'])->name('whatsapp.chat');
    Route::get('/ajax/whatsapp/conversations', [WhatsAppChatController::class, 'conversations'])->name('ajax.whatsapp.conversations');
    Route::get('/ajax/whatsapp/conversations/{conversation}/messages', [WhatsAppChatController::class, 'messages'])->name('ajax.whatsapp.messages');
    Route::post('/ajax/whatsapp/conversations/{conversation}/send-text', [WhatsAppChatController::class, 'sendText'])->name('ajax.whatsapp.send-text');
    Route::post('/ajax/whatsapp/conversations/{conversation}/send-template', [WhatsAppChatController::class, 'sendTemplate'])->name('ajax.whatsapp.send-template');
    Route::get('/ajax/whatsapp/templates', [WhatsAppSettingsController::class, 'templates'])->name('ajax.whatsapp.templates');
    Route::post('/ajax/whatsapp/consents', [WhatsAppSettingsController::class, 'storeConsent'])->name('ajax.whatsapp.consents.store');

    // Phase 5: Analytics, Notifications & GDPR
    Route::get('/analytics/executive', [AnalyticsDashboardController::class, 'executive'])->name('analytics.executive');
    Route::get('/ajax/analytics/executive', [AnalyticsDashboardController::class, 'executiveJson'])->name('ajax.analytics.executive');
    Route::get('/analytics/attribution', [AnalyticsDashboardController::class, 'attribution'])->name('analytics.attribution');
    Route::get('/ajax/analytics/attribution', [AnalyticsDashboardController::class, 'attributionJson'])->name('ajax.analytics.attribution');
    Route::get('/analytics/productivity', [AnalyticsDashboardController::class, 'productivity'])->name('analytics.productivity');
    Route::get('/ajax/analytics/productivity', [AnalyticsDashboardController::class, 'productivityJson'])->name('ajax.analytics.productivity');
    Route::get('/analytics/whatsapp-health', [AnalyticsDashboardController::class, 'whatsappHealth'])->name('analytics.whatsapp-health');
    Route::get('/ajax/analytics/whatsapp-health', [AnalyticsDashboardController::class, 'whatsappHealthJson'])->name('ajax.analytics.whatsapp-health');

    Route::get('/ajax/notifications', [NotificationController::class, 'index'])->name('ajax.notifications.index');
    Route::patch('/ajax/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('ajax.notifications.read');
    Route::post('/ajax/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('ajax.notifications.read-all');

    Route::get('/ajax/contacts/{contact}/gdpr-export', [GdprController::class, 'exportContact'])->name('ajax.contacts.gdpr-export');
    Route::get('/ajax/leads/{lead}/gdpr-export', [GdprController::class, 'exportLead'])->name('ajax.leads.gdpr-export');
    Route::post('/ajax/contacts/{contact}/gdpr-anonymize', [GdprController::class, 'anonymizeContact'])->name('ajax.contacts.gdpr-anonymize');

    Route::middleware(['tenant.role:org_admin,sales_manager'])->group(function (): void {
        Route::get('/settings/pipelines', [PipelineController::class, 'index'])->name('pipelines.index');
        Route::get('/ajax/pipelines', [PipelineController::class, 'list'])->name('ajax.pipelines.index');
        Route::post('/ajax/pipelines', [PipelineController::class, 'store'])->name('ajax.pipelines.store');
        Route::match(['put', 'patch'], '/ajax/pipelines/{pipeline}', [PipelineController::class, 'update'])->name('ajax.pipelines.update');
    });

    Route::middleware(['tenant.role:org_admin'])->group(function (): void {
        Route::delete('/ajax/pipelines/{pipeline}', [PipelineController::class, 'destroy'])->name('ajax.pipelines.destroy');
        Route::patch('/organizations/settings', [OrganizationController::class, 'update'])->name('organizations.update');

        Route::get('/settings/organization', [OrganizationSettingsController::class, 'index'])->name('settings.organization');
        Route::patch('/ajax/organizations/settings', [OrganizationSettingsController::class, 'update'])->name('ajax.organizations.settings');

        Route::get('/settings/team', [TeamMemberController::class, 'index'])->name('settings.team');
        Route::post('/ajax/organizations/invite', [TeamMemberController::class, 'invite'])->name('ajax.organizations.invite');
        Route::post('/ajax/team/members', [TeamMemberController::class, 'store'])->name('ajax.team.members.store');
        Route::patch('/ajax/team/members/{user}/role', [TeamMemberController::class, 'updateRole'])->name('ajax.team.members.role');
        Route::delete('/ajax/team/members/{user}', [TeamMemberController::class, 'destroy'])->name('ajax.team.members.destroy');

        Route::post('/ajax/team/tokens', [ApiTokenController::class, 'store'])->name('ajax.team.tokens.store');
        Route::delete('/ajax/team/tokens/{token}', [ApiTokenController::class, 'destroy'])->name('ajax.team.tokens.destroy');

        Route::get('/settings/custom-fields', [CustomFieldController::class, 'index'])->name('settings.custom-fields');
        Route::post('/ajax/custom-fields', [CustomFieldController::class, 'store'])->name('ajax.custom-fields.store');
        Route::match(['put', 'patch'], '/ajax/custom-fields/{definition}', [CustomFieldController::class, 'update'])->name('ajax.custom-fields.update');
        Route::delete('/ajax/custom-fields/{definition}', [CustomFieldController::class, 'destroy'])->name('ajax.custom-fields.destroy');

        Route::get('/whatsapp/settings', [WhatsAppSettingsController::class, 'index'])->name('whatsapp.settings');
        Route::post('/ajax/whatsapp/connect', [WhatsAppSettingsController::class, 'connect'])->name('ajax.whatsapp.connect');
        Route::delete('/ajax/whatsapp/disconnect', [WhatsAppSettingsController::class, 'disconnect'])->name('ajax.whatsapp.disconnect');
        Route::post('/ajax/whatsapp/templates/sync', [WhatsAppSettingsController::class, 'syncTemplates'])->name('ajax.whatsapp.templates.sync');

        Route::get('/audit-logs', [GdprController::class, 'auditLogs'])->name('audit-logs.index');
        Route::get('/ajax/audit-logs', [GdprController::class, 'auditLogsJson'])->name('ajax.audit-logs.index');
    });
});
