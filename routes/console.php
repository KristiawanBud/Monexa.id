<?php

use Illuminate\Support\Facades\Schedule;
use App\Http\Controllers\Admin\WaGatewayController;
use App\Services\WaGatewayService;

// ── Reset counter harian semua gateway — setiap tengah malam ──────────
Schedule::call(function () {
    app(WaGatewayService::class)->resetDailyCounters();
})->dailyAt('00:00')->name('wa-gateway-reset-daily')->withoutOverlapping();

// ── Release user tidak aktif — setiap Senin jam 02:00 ────────────────
Schedule::call(function () {
    app(WaGatewayService::class)->releaseInactiveUsers();
})->weekly()->mondays()->at('02:00')->name('wa-gateway-release-inactive')->withoutOverlapping();

// ── Recalculate counter — setiap hari jam 03:00 ───────────────────────
Schedule::call(function () {
    app(WaGatewayService::class)->recalculateAllCounters();
})->dailyAt('03:00')->name('wa-gateway-recalculate')->withoutOverlapping();

// ── Monitoring ping gateway — setiap 15 menit ─────────────────────────
// Mengecek status koneksi Fonnte untuk setiap gateway aktif.
// Jika disconnect terdeteksi, alert otomatis dikirim ke WA pribadi owner.
Schedule::call(function () {
    app(\App\Http\Controllers\Admin\WaGatewayController::class)->pingAll();
})->everyFifteenMinutes()->name('wa-gateway-ping-monitoring')->withoutOverlapping();
