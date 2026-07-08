<?php

use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\SuperAdminOnly;
use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            // Routes webhook untuk n8n — didaftarkan terpisah dari web.php
            // supaya mudah dikelola dan tidak tercampur dengan auth flow user.
            Route::middleware('web')->group(base_path('routes/webhook.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ── Global web middleware ──────────────────────────────
        // HandleInertiaRequests di-append ke grup 'web' agar
        // semua request berbasis Inertia mendapat shared props.
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // ── Alias middleware ────────────────────────────────────
        // Didaftarkan di sini (Laravel 13) — BUKAN di Kernel.php.
        // Urutan alias mencerminkan hierarki pengecekan di route:
        //   auth → subscribed → onboarded → (fitur utama)
        $middleware->alias([
            'subscribed'  => CheckSubscription::class,
            'onboarded'   => EnsureOnboarded::class,
            'admin'       => AdminOnly::class,
            'super_admin' => SuperAdminOnly::class,
        ]);

        // ── CSRF Exception untuk Webhook ─────────────────────────
        // n8n adalah server-to-server call, tidak punya CSRF token
        // browser. Keamanan dijaga via X-Webhook-Secret header,
        // bukan CSRF token Laravel.
        $middleware->validateCsrfTokens(except: [
            'webhook/*',
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Konfigurasi penanganan exception bisa ditambahkan di sini
    })
    ->create();
