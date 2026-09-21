<?php

use App\Http\Controllers\Api\AuditController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function () {
    Route::get('audits', [AuditController::class, 'index'])->name('audits.index');

    Route::post('audits', [AuditController::class, 'store'])
        ->middleware('throttle:audit-creation')
        ->name('audits.store');

    Route::get('audits/{audit}', [AuditController::class, 'show'])
        ->whereNumber('audit')
        ->name('audits.show');

    Route::delete('audits/{audit}', [AuditController::class, 'destroy'])
        ->whereNumber('audit')
        ->name('audits.destroy');
});
