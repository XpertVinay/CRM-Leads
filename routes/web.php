<?php

use App\Http\Controllers\LocaleController;
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
use App\Modules\Subscriptions\Controllers\CouponController;
use App\Modules\Subscriptions\Controllers\SubscriptionController;
use App\Modules\Tenancy\Controllers\ApiTokenController;
use App\Modules\Tenancy\Controllers\AuthController;
use App\Modules\Tenancy\Controllers\DashboardController;
use App\Modules\Tenancy\Controllers\GlobalSearchController;
use App\Modules\Tenancy\Controllers\InvitationAcceptanceController;
use App\Modules\Tenancy\Controllers\OrganizationController;
use App\Modules\Tenancy\Controllers\OrganizationSettingsController;
use App\Modules\Tenancy\Controllers\RbacManagementController;
use App\Modules\Tenancy\Controllers\RbacMenuController;
use App\Modules\Tenancy\Controllers\TeamMemberController;
use App\Modules\Tracking\Controllers\PublicLeadCaptureController;
use App\Modules\Tracking\Controllers\RedirectTrackingLinkController;
use App\Modules\Tracking\Controllers\TrackingLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

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
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

Route::get('/invitations/{token}/accept', [InvitationAcceptanceController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('invitations.show');
Route::post('/invitations/{token}/accept', [InvitationAcceptanceController::class, 'accept'])
    ->middleware('throttle:10,1')
    ->name('invitations.accept');

Route::middleware(['auth'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::post('/ajax/organizations/switch', [OrganizationController::class, 'switch'])->name('ajax.organizations.switch');

    Route::middleware(['superadmin'])->group(function (): void {
        Route::patch('/superadmin/organizations/{organization}', [OrganizationController::class, 'superadminUpdate'])->name('superadmin.organizations.update');
        Route::delete('/superadmin/organizations/{organization}', [OrganizationController::class, 'superadminDestroy'])->name('superadmin.organizations.destroy');
        Route::get('/superadmin/rbac', [RbacManagementController::class, 'index'])->name('superadmin.rbac.index');
        Route::post('/superadmin/rbac/roles', [RbacManagementController::class, 'storeRole'])->name('superadmin.rbac.roles.store');
        Route::post('/superadmin/rbac/roles/{role}', [RbacManagementController::class, 'updateRole'])->name('superadmin.rbac.roles.update');
        Route::delete('/superadmin/rbac/roles/{role}', [RbacManagementController::class, 'destroyRole'])->name('superadmin.rbac.roles.destroy');
        Route::post('/superadmin/rbac/permissions', [RbacManagementController::class, 'storePermission'])->name('superadmin.rbac.permissions.store');
        Route::patch('/superadmin/rbac/permissions/{permission}', [RbacManagementController::class, 'updatePermission'])->name('superadmin.rbac.permissions.update');
        Route::delete('/superadmin/rbac/permissions/{permission}', [RbacManagementController::class, 'destroyPermission'])->name('superadmin.rbac.permissions.destroy');
        Route::post('/superadmin/rbac/user-overrides', [RbacManagementController::class, 'updateUserOverrides'])->name('superadmin.rbac.user-overrides');
        Route::post('/superadmin/rbac/cache/flush', [RbacManagementController::class, 'flushCache'])->name('superadmin.rbac.cache.flush');

        Route::get('/superadmin/menus', [RbacMenuController::class, 'index'])->name('superadmin.menus.index');
        Route::post('/superadmin/menus', [RbacMenuController::class, 'store'])->name('superadmin.menus.store');
        Route::patch('/superadmin/menus/{menu}', [RbacMenuController::class, 'update'])->name('superadmin.menus.update');
        Route::delete('/superadmin/menus/{menu}', [RbacMenuController::class, 'destroy'])->name('superadmin.menus.destroy');
    });
});

Route::post('/ajax/coupons/validate', [SubscriptionController::class, 'validateCoupon'])
    ->middleware('throttle:30,1')
    ->name('ajax.coupons.validate');

Route::middleware(['auth', 'tenant', 'subscription.active'])->group(function (): void {
    Route::get('/subscription', [SubscriptionController::class, 'index'])->middleware('tenant.permission:subscription.view')->name('subscription.index');
    Route::post('/ajax/subscription/checkout', [SubscriptionController::class, 'checkout'])->middleware('tenant.permission:subscription.view')->name('ajax.subscription.checkout');
    Route::post('/ajax/subscription/verify', [SubscriptionController::class, 'verify'])->middleware('tenant.permission:subscription.view')->name('ajax.subscription.verify');
    Route::post('/ajax/subscription/cancel', [SubscriptionController::class, 'cancel'])->middleware('tenant.permission:subscription.view')->name('ajax.subscription.cancel');
    Route::post('/ajax/subscription/coupons', [SubscriptionController::class, 'storeCoupon'])->middleware('superadmin')->name('ajax.subscription.coupons.store');
    Route::patch('/ajax/subscription/plans/{plan}', [SubscriptionController::class, 'updatePlan'])->middleware('superadmin')->name('ajax.subscription.plans.update');
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('tenant.permission:dashboard.view')->name('dashboard');
    Route::get('/ajax/dashboard/metrics', [DashboardController::class, 'metricsJson'])->middleware('tenant.permission:dashboard.view')->name('ajax.dashboard.metrics');
    Route::get('/ajax/search', GlobalSearchController::class)->name('ajax.search');

    Route::get('/campaigns', [CampaignController::class, 'index'])->middleware('tenant.permission:campaigns.view')->name('campaigns.index');
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->middleware('tenant.permission:campaigns.view')->name('campaigns.show');
    Route::get('/ajax/campaigns', [CampaignController::class, 'list'])->middleware('tenant.permission:campaigns.view')->name('ajax.campaigns.index');
    Route::post('/ajax/campaigns', [CampaignController::class, 'store'])->middleware('tenant.permission:campaigns.create')->name('ajax.campaigns.store');
    Route::match(['put', 'patch'], '/ajax/campaigns/{campaign}', [CampaignController::class, 'update'])->middleware('tenant.permission:campaigns.update')->name('ajax.campaigns.update');
    Route::delete('/ajax/campaigns/{campaign}', [CampaignController::class, 'destroy'])->middleware('tenant.permission:campaigns.delete')->name('ajax.campaigns.destroy');

    Route::get('/follow-ups', [FollowUpController::class, 'index'])->middleware('tenant.permission:follow_ups.view')->name('follow-ups.index');
    Route::get('/ajax/follow-ups', [FollowUpController::class, 'list'])->middleware('tenant.permission:follow_ups.view')->name('ajax.follow-ups.index');
    Route::post('/ajax/follow-ups', [FollowUpController::class, 'store'])->middleware('tenant.permission:follow_ups.create')->name('ajax.follow-ups.store');
    Route::match(['put', 'patch'], '/ajax/follow-ups/{followUp}', [FollowUpController::class, 'update'])->middleware('tenant.permission:follow_ups.update')->name('ajax.follow-ups.update');
    Route::delete('/ajax/follow-ups/{followUp}', [FollowUpController::class, 'destroy'])->middleware('tenant.permission:follow_ups.delete')->name('ajax.follow-ups.destroy');

    Route::get('/reports', [ReportController::class, 'index'])->middleware('tenant.permission:reports.view')->name('reports.index');
    Route::get('/ajax/reports', [ReportController::class, 'data'])->middleware('tenant.permission:reports.view')->name('ajax.reports.data');
    Route::get('/reports/export', [ReportController::class, 'exportCsv'])->middleware('tenant.permission:reports.view')->name('reports.export');

    Route::get('/tracking-links', [TrackingLinkController::class, 'index'])->middleware('tenant.permission:tracking_links.view')->name('tracking-links.index');
    Route::get('/ajax/tracking-links', [TrackingLinkController::class, 'list'])->middleware('tenant.permission:tracking_links.view')->name('ajax.tracking-links.index');
    Route::post('/ajax/tracking-links', [TrackingLinkController::class, 'store'])->middleware('tenant.permission:tracking_links.create')->name('ajax.tracking-links.store');
    Route::match(['put', 'patch'], '/ajax/tracking-links/{trackingLink}', [TrackingLinkController::class, 'update'])->middleware('tenant.permission:tracking_links.update')->name('ajax.tracking-links.update');
    Route::delete('/ajax/tracking-links/{trackingLink}', [TrackingLinkController::class, 'destroy'])->middleware('tenant.permission:tracking_links.delete')->name('ajax.tracking-links.destroy');
    Route::get('/ajax/attribution/summary', [TrackingLinkController::class, 'attributionSummary'])->middleware('tenant.permission:tracking_links.view,analytics.view')->name('ajax.attribution.summary');

    Route::get('/leads', [LeadController::class, 'index'])->middleware('tenant.permission:leads.view')->name('leads.index');
    Route::get('/leads/export', [LeadController::class, 'exportCsv'])->middleware('tenant.permission:leads.view')->name('leads.export');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->middleware('tenant.permission:leads.view')->name('leads.show');
    Route::get('/ajax/leads', [LeadController::class, 'list'])->middleware('tenant.permission:leads.view')->name('ajax.leads.index');
    Route::post('/ajax/leads', [LeadController::class, 'store'])->middleware('tenant.permission:leads.create')->name('ajax.leads.store');
    Route::post('/ajax/leads/bulk', [LeadController::class, 'bulkAction'])->middleware('tenant.permission:leads.update,leads.delete,leads.assign')->name('ajax.leads.bulk');
    Route::get('/ajax/leads/{lead}', [LeadController::class, 'showJson'])->middleware('tenant.permission:leads.view')->name('ajax.leads.show');
    Route::match(['put', 'patch'], '/ajax/leads/{lead}', [LeadController::class, 'update'])->middleware('tenant.permission:leads.update')->name('ajax.leads.update');
    Route::delete('/ajax/leads/{lead}', [LeadController::class, 'destroy'])->middleware('tenant.permission:leads.delete')->name('ajax.leads.destroy');
    Route::patch('/ajax/leads/{lead}/assign', [LeadController::class, 'assign'])->middleware('tenant.permission:leads.assign')->name('ajax.leads.assign');
    Route::post('/ajax/leads/{lead}/convert', [LeadController::class, 'convert'])->middleware('tenant.permission:leads.convert')->name('ajax.leads.convert');

    Route::get('/ajax/custom-fields', [CustomFieldController::class, 'list'])->middleware('tenant.permission:custom_fields.view')->name('ajax.custom-fields.list');

    // Phase 3: Accounts, Contacts, Deals, Pipelines & Activities
    Route::get('/accounts', [AccountController::class, 'index'])->middleware('tenant.permission:accounts.view')->name('accounts.index');
    Route::get('/accounts/{account}', [AccountController::class, 'show'])->middleware('tenant.permission:accounts.view')->name('accounts.show');
    Route::get('/ajax/accounts', [AccountController::class, 'list'])->middleware('tenant.permission:accounts.view')->name('ajax.accounts.index');
    Route::post('/ajax/accounts', [AccountController::class, 'store'])->middleware('tenant.permission:accounts.create')->name('ajax.accounts.store');
    Route::get('/ajax/accounts/{account}', [AccountController::class, 'showJson'])->middleware('tenant.permission:accounts.view')->name('ajax.accounts.show');
    Route::match(['put', 'patch'], '/ajax/accounts/{account}', [AccountController::class, 'update'])->middleware('tenant.permission:accounts.update')->name('ajax.accounts.update');
    Route::delete('/ajax/accounts/{account}', [AccountController::class, 'destroy'])->middleware('tenant.permission:accounts.delete')->name('ajax.accounts.destroy');

    Route::get('/contacts', [ContactController::class, 'index'])->middleware('tenant.permission:contacts.view')->name('contacts.index');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->middleware('tenant.permission:contacts.view')->name('contacts.show');
    Route::get('/ajax/contacts', [ContactController::class, 'list'])->middleware('tenant.permission:contacts.view')->name('ajax.contacts.index');
    Route::post('/ajax/contacts', [ContactController::class, 'store'])->middleware('tenant.permission:contacts.create')->name('ajax.contacts.store');
    Route::get('/ajax/contacts/{contact}', [ContactController::class, 'showJson'])->middleware('tenant.permission:contacts.view')->name('ajax.contacts.show');
    Route::match(['put', 'patch'], '/ajax/contacts/{contact}', [ContactController::class, 'update'])->middleware('tenant.permission:contacts.update')->name('ajax.contacts.update');
    Route::delete('/ajax/contacts/{contact}', [ContactController::class, 'destroy'])->middleware('tenant.permission:contacts.delete')->name('ajax.contacts.destroy');

    Route::get('/deals', [DealController::class, 'index'])->middleware('tenant.permission:deals.view')->name('deals.index');
    Route::get('/deals/{deal}', [DealController::class, 'show'])->middleware('tenant.permission:deals.view')->name('deals.show');
    Route::get('/ajax/deals', [DealController::class, 'list'])->middleware('tenant.permission:deals.view')->name('ajax.deals.index');
    Route::post('/ajax/deals', [DealController::class, 'store'])->middleware('tenant.permission:deals.create')->name('ajax.deals.store');
    Route::get('/ajax/deals/{deal}', [DealController::class, 'showJson'])->middleware('tenant.permission:deals.view')->name('ajax.deals.show');
    Route::match(['put', 'patch'], '/ajax/deals/{deal}', [DealController::class, 'update'])->middleware('tenant.permission:deals.update')->name('ajax.deals.update');
    Route::delete('/ajax/deals/{deal}', [DealController::class, 'destroy'])->middleware('tenant.permission:deals.delete')->name('ajax.deals.destroy');
    Route::patch('/ajax/deals/{deal}/stage', [DealController::class, 'transitionStage'])->middleware('tenant.permission:deals.update')->name('ajax.deals.stage');

    Route::get('/ajax/activities', [ActivityController::class, 'list'])->middleware('tenant.permission:accounts.view,contacts.view,deals.view,leads.view')->name('ajax.activities.index');
    Route::post('/ajax/activities', [ActivityController::class, 'store'])->middleware('tenant.permission:accounts.update,contacts.update,deals.update,leads.update')->name('ajax.activities.store');

    // Phase 4: WhatsApp Cloud API & Coexistence
    Route::middleware(['whatsapp.configured'])->group(function (): void {
        Route::get('/whatsapp/chat', [WhatsAppChatController::class, 'index'])->middleware('tenant.permission:whatsapp.view')->name('whatsapp.chat');
        Route::get('/ajax/whatsapp/conversations', [WhatsAppChatController::class, 'conversations'])->middleware('tenant.permission:whatsapp.view')->name('ajax.whatsapp.conversations');
        Route::get('/ajax/whatsapp/conversations/{conversation}/messages', [WhatsAppChatController::class, 'messages'])->middleware('tenant.permission:whatsapp.view')->name('ajax.whatsapp.messages');
        Route::post('/ajax/whatsapp/conversations/{conversation}/send-text', [WhatsAppChatController::class, 'sendText'])->middleware('tenant.permission:whatsapp.send')->name('ajax.whatsapp.send-text');
        Route::post('/ajax/whatsapp/conversations/{conversation}/send-template', [WhatsAppChatController::class, 'sendTemplate'])->middleware('tenant.permission:whatsapp.send')->name('ajax.whatsapp.send-template');
        Route::get('/ajax/whatsapp/templates', [WhatsAppSettingsController::class, 'templates'])->middleware('tenant.permission:whatsapp.view')->name('ajax.whatsapp.templates');
        Route::post('/ajax/whatsapp/consents', [WhatsAppSettingsController::class, 'storeConsent'])->middleware('tenant.permission:whatsapp.send')->name('ajax.whatsapp.consents.store');
    });

    // Phase 5: Analytics, Notifications & GDPR
    Route::get('/analytics/executive', [AnalyticsDashboardController::class, 'executive'])->middleware('tenant.permission:analytics.view')->name('analytics.executive');
    Route::get('/ajax/analytics/executive', [AnalyticsDashboardController::class, 'executiveJson'])->middleware('tenant.permission:analytics.view')->name('ajax.analytics.executive');
    Route::get('/analytics/attribution', [AnalyticsDashboardController::class, 'attribution'])->middleware('tenant.permission:analytics.view')->name('analytics.attribution');
    Route::get('/ajax/analytics/attribution', [AnalyticsDashboardController::class, 'attributionJson'])->middleware('tenant.permission:analytics.view')->name('ajax.analytics.attribution');
    Route::get('/analytics/productivity', [AnalyticsDashboardController::class, 'productivity'])->middleware('tenant.permission:analytics.view')->name('analytics.productivity');
    Route::get('/ajax/analytics/productivity', [AnalyticsDashboardController::class, 'productivityJson'])->middleware('tenant.permission:analytics.view')->name('ajax.analytics.productivity');
    Route::get('/analytics/whatsapp-health', [AnalyticsDashboardController::class, 'whatsappHealth'])->middleware('tenant.permission:analytics.view')->name('analytics.whatsapp-health');
    Route::get('/ajax/analytics/whatsapp-health', [AnalyticsDashboardController::class, 'whatsappHealthJson'])->middleware('tenant.permission:analytics.view')->name('ajax.analytics.whatsapp-health');

    Route::get('/ajax/notifications', [NotificationController::class, 'index'])->name('ajax.notifications.index');
    Route::patch('/ajax/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('ajax.notifications.read');
    Route::post('/ajax/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('ajax.notifications.read-all');

    Route::get('/ajax/contacts/{contact}/gdpr-export', [GdprController::class, 'exportContact'])->middleware('tenant.permission:contacts.view')->name('ajax.contacts.gdpr-export');
    Route::get('/ajax/leads/{lead}/gdpr-export', [GdprController::class, 'exportLead'])->middleware('tenant.permission:leads.view')->name('ajax.leads.gdpr-export');
    Route::post('/ajax/contacts/{contact}/gdpr-anonymize', [GdprController::class, 'anonymizeContact'])->middleware('tenant.permission:contacts.delete')->name('ajax.contacts.gdpr-anonymize');

    Route::get('/settings/pipelines', [PipelineController::class, 'index'])
        ->middleware('tenant.permission:pipelines.view')
        ->name('pipelines.index');
    Route::get('/ajax/pipelines', [PipelineController::class, 'list'])
        ->middleware('tenant.permission:pipelines.view')
        ->name('ajax.pipelines.index');
    Route::post('/ajax/pipelines', [PipelineController::class, 'store'])
        ->middleware('tenant.permission:pipelines.create')
        ->name('ajax.pipelines.store');
    Route::match(['put', 'patch'], '/ajax/pipelines/{pipeline}', [PipelineController::class, 'update'])
        ->middleware('tenant.permission:pipelines.update')
        ->name('ajax.pipelines.update');

    Route::delete('/ajax/pipelines/{pipeline}', [PipelineController::class, 'destroy'])
        ->middleware('tenant.permission:pipelines.delete')
        ->name('ajax.pipelines.destroy');

    Route::middleware(['tenant.permission:organization.settings'])->group(function (): void {
        Route::patch('/organizations/settings', [OrganizationController::class, 'update'])->name('organizations.update');
        Route::get('/settings/organization', [OrganizationSettingsController::class, 'index'])->name('settings.organization');
        Route::patch('/ajax/organizations/settings', [OrganizationSettingsController::class, 'update'])->name('ajax.organizations.settings');
    });

    Route::middleware(['tenant.permission:team.manage'])->group(function (): void {
        Route::get('/settings/team', [TeamMemberController::class, 'index'])->name('settings.team');
        Route::post('/ajax/organizations/invite', [TeamMemberController::class, 'invite'])->name('ajax.organizations.invite');
        Route::post('/ajax/team/members', [TeamMemberController::class, 'store'])->name('ajax.team.members.store');
        Route::patch('/ajax/team/members/{user}/role', [TeamMemberController::class, 'updateRole'])->name('ajax.team.members.role');
        Route::delete('/ajax/team/members/{user}', [TeamMemberController::class, 'destroy'])->name('ajax.team.members.destroy');
    });

    Route::middleware(['tenant.permission:tokens.manage'])->group(function (): void {
        Route::post('/ajax/team/tokens', [ApiTokenController::class, 'store'])->name('ajax.team.tokens.store');
        Route::delete('/ajax/team/tokens/{token}', [ApiTokenController::class, 'destroy'])->name('ajax.team.tokens.destroy');
    });

    Route::middleware(['tenant.permission:custom_fields.manage'])->group(function (): void {
        Route::get('/settings/custom-fields', [CustomFieldController::class, 'index'])->name('settings.custom-fields');
        Route::post('/ajax/custom-fields', [CustomFieldController::class, 'store'])->name('ajax.custom-fields.store');
        Route::match(['put', 'patch'], '/ajax/custom-fields/{definition}', [CustomFieldController::class, 'update'])->name('ajax.custom-fields.update');
        Route::delete('/ajax/custom-fields/{definition}', [CustomFieldController::class, 'destroy'])->name('ajax.custom-fields.destroy');
    });

    Route::middleware(['tenant.permission:whatsapp.settings'])->group(function (): void {
        Route::get('/whatsapp/settings', [WhatsAppSettingsController::class, 'index'])->name('whatsapp.settings');
        Route::post('/ajax/whatsapp/connect', [WhatsAppSettingsController::class, 'connect'])->name('ajax.whatsapp.connect');
        Route::delete('/ajax/whatsapp/disconnect', [WhatsAppSettingsController::class, 'disconnect'])->name('ajax.whatsapp.disconnect');
        Route::post('/ajax/whatsapp/templates/sync', [WhatsAppSettingsController::class, 'syncTemplates'])->name('ajax.whatsapp.templates.sync');
    });

    Route::middleware(['tenant.permission:audit_logs.view'])->group(function (): void {
        Route::get('/audit-logs', [GdprController::class, 'auditLogs'])->name('audit-logs.index');
        Route::get('/ajax/audit-logs', [GdprController::class, 'auditLogsJson'])->name('ajax.audit-logs.index');
    });

    Route::middleware(['superadmin'])->group(function (): void {
        Route::get('/coupons', [CouponController::class, 'index'])->name('coupons.index');
        Route::get('/ajax/coupons', [CouponController::class, 'list'])->name('ajax.coupons.index');
        Route::post('/ajax/coupons', [CouponController::class, 'store'])->name('ajax.coupons.store');
        Route::match(['put', 'patch'], '/ajax/coupons/{coupon}', [CouponController::class, 'update'])->name('ajax.coupons.update');
        Route::delete('/ajax/coupons/{coupon}', [CouponController::class, 'destroy'])->name('ajax.coupons.destroy');

        Route::post('/ajax/whatsapp/templates', [WhatsAppSettingsController::class, 'storeTemplate'])->name('ajax.whatsapp.templates.store');
        Route::match(['put', 'patch'], '/ajax/whatsapp/templates/{template}', [WhatsAppSettingsController::class, 'updateTemplate'])->name('ajax.whatsapp.templates.update');
        Route::delete('/ajax/whatsapp/templates/{template}', [WhatsAppSettingsController::class, 'destroyTemplate'])->name('ajax.whatsapp.templates.destroy');

        Route::post('/ajax/audit-logs', [GdprController::class, 'storeAuditLog'])->name('ajax.audit-logs.store');
        Route::match(['put', 'patch'], '/ajax/audit-logs/{auditLog}', [GdprController::class, 'updateAuditLog'])->name('ajax.audit-logs.update');
        Route::delete('/ajax/audit-logs/{auditLog}', [GdprController::class, 'destroyAuditLog'])->name('ajax.audit-logs.destroy');
    });
});
