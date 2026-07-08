<?php

use App\Http\Controllers\Admin\WaGatewayController;
use App\Http\Controllers\Admin\IconController;
use App\Http\Controllers\Admin\CuanAiRulesController;
use App\Http\Controllers\Admin\SubscriptionAdminController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\BankAdminController;
use App\Http\Controllers\Admin\CategoryAdminController;
use App\Http\Controllers\Admin\BrandingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/users', [UserManagementController::class, 'index'])->name('users');
        Route::put('/users/{user}/suspend', [UserManagementController::class, 'suspend'])->name('users.suspend');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');

        Route::middleware('super_admin')->group(function () {
            Route::put('/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.role');

            Route::get('/packages', [PackageController::class, 'index'])->name('packages');
            Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');
            Route::put('/packages/{package}', [PackageController::class, 'update'])->name('packages.update');
            Route::delete('/packages/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');

            Route::get('/subscriptions', [SubscriptionAdminController::class, 'index'])->name('subscriptions');
            Route::put('/subscriptions/{subscription}', [SubscriptionAdminController::class, 'update'])->name('subscriptions.update');

            Route::get('/banks', [BankAdminController::class, 'index'])->name('banks');
            Route::post('/banks', [BankAdminController::class, 'store'])->name('banks.store');
            Route::put('/banks/{bank}', [BankAdminController::class, 'update'])->name('banks.update');
            Route::delete('/banks/{bank}/logo', [BankAdminController::class, 'removeLogo'])->name('banks.remove-logo');

            Route::get('/categories', [CategoryAdminController::class, 'index'])->name('categories');
            Route::post('/categories', [CategoryAdminController::class, 'store'])->name('categories.store');
            Route::post('/categories/{category}/icon', [CategoryAdminController::class, 'uploadIcon'])->name('categories.icon.upload');
            Route::delete('/categories/{category}/icon', [CategoryAdminController::class, 'resetIcon'])->name('categories.icon.reset');
            Route::delete('/categories/{category}', [CategoryAdminController::class, 'destroy'])->name('categories.destroy');

            Route::put('/branding', [BrandingController::class, 'update'])->name('branding');

            Route::get('/cuan-ai-rules', [CuanAiRulesController::class, 'index'])->name('cuan-ai-rules');
            Route::put('/cuan-ai-rules', [CuanAiRulesController::class, 'update'])->name('cuan-ai-rules.update');
            Route::delete('/cuan-ai-rules', [CuanAiRulesController::class, 'reset'])->name('cuan-ai-rules.reset');

            Route::get('/icons', [IconController::class, 'index'])->name('icons');
            Route::post('/icons/{slug}', [IconController::class, 'upload'])->name('icons.upload');
            Route::delete('/icons/{slug}', [IconController::class, 'reset'])->name('icons.reset');
        });

    });

Route::middleware('super_admin')->prefix('wa-gateway')->name('admin.gateway.')->group(function () {
    Route::get('/',                              [WaGatewayController::class, 'index'])->name('index');
    Route::post('/',                             [WaGatewayController::class, 'store'])->name('store');
    Route::put('/{gateway}',                     [WaGatewayController::class, 'update'])->name('update');
    Route::delete('/{gateway}',                  [WaGatewayController::class, 'destroy'])->name('destroy');
    Route::post('/{gateway}/test',               [WaGatewayController::class, 'test'])->name('test');
    Route::get('/{gateway}/users',               [WaGatewayController::class, 'users'])->name('users');
    Route::post('/reassign',                     [WaGatewayController::class, 'reassign'])->name('reassign');
    Route::post('/release-inactive',             [WaGatewayController::class, 'releaseInactive'])->name('release-inactive');
    Route::post('/recalculate',                  [WaGatewayController::class, 'recalculate'])->name('recalculate');
    Route::post('/owner-number',                 [WaGatewayController::class, 'saveOwnerNumber'])->name('owner-number');
    Route::post('/ping-all',                     [WaGatewayController::class, 'pingAll'])->name('ping-all');
});
