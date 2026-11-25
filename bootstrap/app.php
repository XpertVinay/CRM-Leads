<?php

use App\Http\Middleware\SetLocale;
use App\Modules\Tenancy\Middleware\AuthenticateApiToken;
use App\Modules\Tenancy\Middleware\AuthorizeTenantRole;
use App\Modules\Tenancy\Middleware\EnsureSuperadmin;
use App\Modules\Tenancy\Middleware\EnsureTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'tenant' => EnsureTenantContext::class,
            'tenant.role' => AuthorizeTenantRole::class,
            'superadmin' => EnsureSuperadmin::class,
            'api.token' => AuthenticateApiToken::class,
        ]);

        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            EnsureTenantContext::class,
        );

        $middleware->appendToPriorityList(
            EnsureTenantContext::class,
            AuthorizeTenantRole::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('ajax/*') || $request->expectsJson(),
        );
    })->create();
