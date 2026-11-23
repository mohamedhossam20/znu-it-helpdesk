<?php

use App\Http\Controllers\Admin\ImportTemplateController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\Admin\RoomQrLabelsController;
use App\Http\Controllers\Api\V1\TicketActionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('auth')->prefix('admin-tools')->name('admin.')->group(function () {
    Route::get('rooms/qr-labels', RoomQrLabelsController::class)->name('rooms.qr');
    Route::get('import-templates/{type}', ImportTemplateController::class)->name('import-templates');
    Route::get('reports/excel', [ReportExportController::class, 'excel'])->name('reports.excel');
    Route::get('reports/print', [ReportExportController::class, 'print'])->name('reports.print');
    // Same checks as the API route, reachable with the panel's session cookie.
    Route::get('tickets/{ticket}/photos/{media}', [TicketActionController::class, 'showPhoto'])->name('tickets.photos');
});
