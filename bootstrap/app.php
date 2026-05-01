<?php

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\AdminRequire2FA;
use App\Http\Middleware\CheckTenantBloqueado;
use App\Http\Middleware\EnsureFullFlowSubscriptionActive;
use App\Http\Middleware\EnsureHasRole;
use App\Http\Middleware\EnsureTenantSelected;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Webhook do FullFlow é assinado via HMAC; CSRF não se aplica.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'tenant' => EnsureTenantSelected::class,
            'tenant.ativo' => CheckTenantBloqueado::class,
            'subscription.active' => EnsureFullFlowSubscriptionActive::class,
            'role' => EnsureHasRole::class,
            'admin.auth' => AdminAuthenticate::class,
            'admin.require2fa' => AdminRequire2FA::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
