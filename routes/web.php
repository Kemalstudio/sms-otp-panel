<?php

use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\OtpLogController;
use App\Http\Controllers\PairingCodeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');

    /*
     * Everything under a project is gated by ProjectPolicy::view (ownership),
     * not just by `auth`. scopeBindings() additionally forces child models
     * (api keys) to be resolved through the project relation, so a key from
     * another project 404s instead of being revoked.
     */
    Route::prefix('projects/{project}')
        ->name('projects.')
        ->middleware('can:view,project')
        ->scopeBindings()
        ->group(function () {
            Route::get('/', [ProjectController::class, 'show'])->name('show');

            Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
            Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
            Route::patch('devices/{device}', [DeviceController::class, 'update'])
                ->middleware('can:update,project')
                ->name('devices.update');

            // Issuing a pairing code is a write on the project, so it needs
            // `update` on top of the group's `view`.
            Route::post('pairing-codes', [PairingCodeController::class, 'store'])
                ->middleware('can:update,project')
                ->name('pairing-codes.store');

            Route::get('api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
            Route::post('api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
            Route::delete('api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

            Route::get('logs', [OtpLogController::class, 'index'])->name('logs.index');

            Route::get('webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
            Route::middleware('can:update,project')->group(function () {
                Route::patch('webhooks', [WebhookController::class, 'update'])->name('webhooks.update');
                Route::post('webhooks/rotate', [WebhookController::class, 'rotate'])->name('webhooks.rotate');
                Route::post('webhooks/test', [WebhookController::class, 'test'])->name('webhooks.test');
            });
        });
});

require __DIR__.'/auth.php';
