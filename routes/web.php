<?php

use App\Modules\CustomFields\Controllers\CustomFieldController;
use App\Modules\Leads\Controllers\LeadController;
use App\Modules\Tenancy\Controllers\ApiTokenController;
use App\Modules\Tenancy\Controllers\AuthController;
use App\Modules\Tenancy\Controllers\DashboardController;
use App\Modules\Tenancy\Controllers\InvitationAcceptanceController;
use App\Modules\Tenancy\Controllers\OrganizationController;
use App\Modules\Tenancy\Controllers\TeamMemberController;
use App\Modules\Tracking\Controllers\RedirectTrackingLinkController;
use App\Modules\Tracking\Controllers\TrackingLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/l/{code}', RedirectTrackingLinkController::class)
    ->middleware('throttle:120,1')
    ->name('tracking-links.redirect');

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

    Route::get('/tracking-links', [TrackingLinkController::class, 'index'])->name('tracking-links.index');
    Route::get('/ajax/tracking-links', [TrackingLinkController::class, 'list'])->name('ajax.tracking-links.index');
    Route::post('/ajax/tracking-links', [TrackingLinkController::class, 'store'])->name('ajax.tracking-links.store');
    Route::match(['put', 'patch'], '/ajax/tracking-links/{trackingLink}', [TrackingLinkController::class, 'update'])->name('ajax.tracking-links.update');
    Route::delete('/ajax/tracking-links/{trackingLink}', [TrackingLinkController::class, 'destroy'])->name('ajax.tracking-links.destroy');
    Route::get('/ajax/attribution/summary', [TrackingLinkController::class, 'attributionSummary'])->name('ajax.attribution.summary');

    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::get('/ajax/leads', [LeadController::class, 'list'])->name('ajax.leads.index');
    Route::post('/ajax/leads', [LeadController::class, 'store'])->name('ajax.leads.store');
    Route::get('/ajax/leads/{lead}', [LeadController::class, 'showJson'])->name('ajax.leads.show');
    Route::match(['put', 'patch'], '/ajax/leads/{lead}', [LeadController::class, 'update'])->name('ajax.leads.update');
    Route::delete('/ajax/leads/{lead}', [LeadController::class, 'destroy'])->name('ajax.leads.destroy');
    Route::patch('/ajax/leads/{lead}/assign', [LeadController::class, 'assign'])->name('ajax.leads.assign');
    Route::post('/ajax/leads/{lead}/convert', [LeadController::class, 'convert'])->name('ajax.leads.convert');

    Route::get('/ajax/custom-fields', [CustomFieldController::class, 'list'])->name('ajax.custom-fields.list');

    Route::middleware(['tenant.role:org_admin'])->group(function (): void {
        Route::patch('/organizations/settings', [OrganizationController::class, 'update'])->name('organizations.update');

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
    });
});
