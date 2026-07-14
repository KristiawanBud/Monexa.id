<?php

use App\Http\Controllers\App\AccountController;
use App\Http\Controllers\App\AssetController;
use App\Http\Controllers\App\BillController;
use App\Http\Controllers\App\BudgetController;
use App\Http\Controllers\App\CuanAiController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\ImportController;
use App\Http\Controllers\App\OnboardingController;
use App\Http\Controllers\App\ReceiptScanController;
use App\Http\Controllers\App\ReportController;
use App\Http\Controllers\App\SavingGoalController;
use App\Http\Controllers\App\TransactionController;
use App\Http\Controllers\App\WalletController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

// ── Auth ─────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Google OAuth (aktifkan setelah composer require laravel/socialite)
Route::get('/auth/google', [LoginController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [LoginController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// ── Subscription Expired ─────────────────────────────
Route::get('/subscription/expired', function () {
    return inertia('App/SubscriptionExpired');
})->middleware('auth')->name('subscription.expired');

// ── Onboarding ───────────────────────────────────────
Route::middleware(['auth'])->prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/profile', [OnboardingController::class, 'step1'])->name('step1');
    Route::post('/profile', [OnboardingController::class, 'saveStep1'])->name('save-step1');
    Route::get('/bank', [OnboardingController::class, 'step2'])->name('step2');
    Route::post('/bank', [OnboardingController::class, 'saveStep2'])->name('save-step2');
    Route::get('/done', [OnboardingController::class, 'step3'])->name('step3');
});

// ── App (User) ───────────────────────────────────────
Route::middleware(['auth', 'subscribed', 'onboarded'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Dompet — gabungan kelola dompet + transaksi + tagihan
    Route::prefix('dompet')->name('dompet.')->group(function () {
        Route::get('/', [TransactionController::class, 'index'])->name('index');
        Route::post('/', [TransactionController::class, 'store'])->name('store');
        Route::put('/{transaction}', [TransactionController::class, 'update'])->name('update');
        Route::delete('/{transaction}', [TransactionController::class, 'destroy'])->name('destroy');
        Route::get('/export', [TransactionController::class, 'exportCsv'])->name('export');
        Route::get('/{transaction}/logs', [TransactionController::class, 'editLogs'])->name('logs');
    });

    // Tabungan
    Route::prefix('saving')->name('saving.')->group(function () {
        Route::get('/', [SavingGoalController::class, 'index'])->name('index');
        Route::post('/', [SavingGoalController::class, 'store'])->name('store');
        Route::post('/{goal}/deposit', [SavingGoalController::class, 'deposit'])->name('deposit');
        Route::get('/{goal}/deposits', [SavingGoalController::class, 'deposits'])->name('deposits');
        Route::delete('/{goal}', [SavingGoalController::class, 'destroy'])->name('destroy');
    });

    // Tagihan
    Route::prefix('bills')->name('bills.')->group(function () {
        Route::get('/', [BillController::class, 'index'])->name('index');
        Route::post('/', [BillController::class, 'store'])->name('store');
        Route::put('/{bill}', [BillController::class, 'update'])->name('update');
        Route::post('/{bill}/pay', [BillController::class, 'pay'])->name('pay');
        Route::delete('/{bill}', [BillController::class, 'destroy'])->name('destroy');
    });

    // Laporan
    Route::get('/report', [ReportController::class, 'index'])->name('report');
    Route::get('/report/export/pdf', [ReportController::class, 'exportPdf'])->name('report.export-pdf');
    Route::get('/report/export/excel', [ReportController::class, 'exportExcel'])->name('report.export-excel');
    Route::post('/report/send-whatsapp', [ReportController::class, 'sendToWhatsApp'])->name('report.send-whatsapp');

    // Akun
    Route::get('/account', [AccountController::class, 'index'])->name('account');

    // Aset
    Route::prefix('asset')->name('asset.')->group(function () {
        Route::get('/', [AssetController::class, 'index'])->name('index');
        Route::post('/', [AssetController::class, 'store'])->name('store');
        Route::put('/{asset}', [AssetController::class, 'update'])->name('update');
        Route::delete('/{asset}', [AssetController::class, 'destroy'])->name('destroy');
    });

    // Scan Struk
    Route::prefix('receipt')->name('receipt.')->group(function () {
        Route::get('/', [ReceiptScanController::class, 'index'])->name('index');
        Route::post('/upload', [ReceiptScanController::class, 'upload'])->name('upload');
        Route::post('/{scan}/confirm', [ReceiptScanController::class, 'confirm'])->name('confirm');
    });

    // CuanAI
    Route::prefix('ai')->name('ai.')->group(function () {
        Route::post('/chat', [CuanAiController::class, 'chat'])->name('chat');
        Route::get('/history', [CuanAiController::class, 'history'])->name('history');
        Route::post('/reset', [CuanAiController::class, 'reset'])->name('reset');
    });

    // Import Transaksi
    Route::prefix('import')->name('import.')->group(function () {
        Route::get('/', [ImportController::class, 'index'])->name('index');
        Route::post('/upload', [ImportController::class, 'upload'])->name('upload');
        Route::post('/{session}/confirm', [ImportController::class, 'confirm'])->name('confirm');
        Route::get('/template/{type?}', [ImportController::class, 'downloadTemplate'])->name('template');
    });

    // Budget Management
    Route::prefix('budget')->name('budget.')->group(function () {
        Route::get('/', [BudgetController::class, 'index'])->name('index');
        Route::post('/', [BudgetController::class, 'upsert'])->name('upsert');
        Route::post('/copy-last-month', [BudgetController::class, 'copyFromLastMonth'])->name('copy');
    });

    // Toggle hide balance (AJAX)
    Route::post('/dashboard/toggle-balance', [DashboardController::class, 'toggleHideBalance'])
        ->name('dashboard.toggle-balance');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::put('/account/theme', [AccountController::class, 'updateTheme'])->name('account.theme');
    Route::post('/account/reset-data', [AccountController::class, 'resetData'])->name('account.reset-data');

    // Wallets
    // Wallets (dipindah ke bawah Dompet, bukan lagi di Akun)
    Route::post('/dompet/wallets', [WalletController::class, 'store'])->name('wallets.store');
    Route::put('/dompet/wallets/{wallet}', [WalletController::class, 'update'])->name('wallets.update');
    Route::delete('/dompet/wallets/{wallet}', [WalletController::class, 'destroy'])->name('wallets.destroy');
    Route::patch('/dompet/wallets/{wallet}/archive', [WalletController::class, 'archive'])->name('wallets.archive');
    Route::post('/dompet/transfer', [WalletController::class, 'transfer'])->name('wallets.transfer');
    Route::delete('/dompet/transfer/{walletTransfer}', [WalletController::class, 'destroyTransfer'])->name('wallets.transfer.destroy');

});

// ── Admin ─────────────────────────────────────────────
require __DIR__.'/admin.php';
