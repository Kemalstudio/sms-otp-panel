<?php

use App\Http\Controllers\Api\DevicePairingController;
use App\Http\Controllers\Api\DeviceStatusController;
use App\Http\Controllers\Api\OtpController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Gateway API (v1)
|--------------------------------------------------------------------------
|
| Three audiences, three credentials:
|   - /devices/pair          public, the short-lived pairing code is the credential
|   - /otp/*                 customer integrations, X-Api-Key
|                            (+ optional Idempotency-Key)
|   - /devices/heartbeat|report-status   paired phones, X-Device-Token
|
*/

Route::prefix('v1')->group(function () {
    // The only unauthenticated route, so it carries its own throttle to keep
    // the 6-character code space from being walked.
    Route::post('devices/pair', [DevicePairingController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('api.devices.pair');

    /*
     * idempotency идёт после api.key: запомненный ответ привязан к ключу
     * проекта, иначе "order-42" двух разных клиентов столкнулись бы.
     */
    Route::middleware(['api.key', 'idempotency'])->group(function () {
        Route::post('otp/send', [OtpController::class, 'send'])->name('api.otp.send');
        Route::post('otp/verify', [OtpController::class, 'verify'])->name('api.otp.verify');
        Route::get('otp/{otp}', [OtpController::class, 'show'])
            ->whereNumber('otp')
            ->name('api.otp.show');
    });

    Route::middleware('device.token')->group(function () {
        Route::post('devices/heartbeat', [DeviceStatusController::class, 'heartbeat'])
            ->name('api.devices.heartbeat');
        Route::post('devices/report-status', [DeviceStatusController::class, 'reportStatus'])
            ->name('api.devices.report-status');
    });
});
