<?php

use App\Http\Controllers\Webhook\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────
// WEBHOOK ROUTES — Dipanggil oleh n8n (server-to-server)
//
// TIDAK pakai middleware 'auth' karena ini bukan request dari
// browser user, melainkan dari workflow n8n. Keamanan dijaga
// lewat header X-Webhook-Secret yang dicocokkan dengan
// N8N_WEBHOOK_SECRET di .env (lihat WhatsAppWebhookController).
//
// CSRF protection di-skip otomatis untuk route ini karena
// prefix 'webhook' — pastikan ditambahkan ke except() pada
// VerifyCsrfToken middleware atau bootstrap/app.php jika perlu.
// ─────────────────────────────────────────────────────────

Route::prefix('webhook')->name('webhook.')->group(function () {

    Route::prefix('whatsapp')->name('whatsapp.')->group(function () {

        // Endpoint utama — n8n kirim pesan masuk ke sini
        Route::post('/', [WhatsAppWebhookController::class, 'receive'])->name('receive');

        // Endpoint test koneksi — dipanggil sekali saat setup n8n
        Route::get('/ping', [WhatsAppWebhookController::class, 'ping'])->name('ping');

        // Endpoint bantu — n8n bisa query nomor gateway mana yang
        // harus dipakai untuk membalas user tertentu
        Route::get('/gateway-for/{userId}', [WhatsAppWebhookController::class, 'gatewayForUser'])
            ->name('gateway-for');

    });

});
