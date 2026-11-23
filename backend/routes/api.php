<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\TicketActionController;
use App\Http\Controllers\Api\V1\TicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::controller(LookupController::class)->group(function () {
            Route::get('me/context', 'context')->name('me.context');
            Route::get('buildings', 'buildings')->name('buildings.index');
            Route::get('floors/{floor}/rooms', 'rooms')->name('floors.rooms');
            Route::get('rooms/{room}/assets', 'roomAssets')->name('rooms.assets');
            Route::get('rooms/by-qr/{token}', 'roomByQr')->name('rooms.by-qr');
            Route::get('categories', 'categories')->name('categories.index');
        });

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read', [NotificationController::class, 'read'])->name('notifications.read');

        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::post('tickets', [TicketController::class, 'store'])->middleware('throttle:tickets')->name('tickets.store');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');

        Route::controller(TicketActionController::class)->prefix('tickets/{ticket}')->name('tickets.')->group(function () {
            Route::post('comments', 'comment')->middleware('throttle:ticket-writes')->name('comments.store');
            Route::post('photos', 'photos')->middleware('throttle:ticket-writes')->name('photos.store');
            Route::get('photos/{media}', 'showPhoto')->name('photos.show');
            Route::post('cancel', 'cancel')->name('cancel');
            Route::post('confirm', 'confirm')->name('confirm');
            Route::post('reopen', 'reopen')->name('reopen');
            Route::post('rating', 'rate')->name('rating');
        });
    });
});
