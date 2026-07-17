<?php

use App\Http\Controllers\Api\AuditController;
use Illuminate\Support\Facades\Route;

Route::apiResource('audits', AuditController::class)->only(['index', 'store', 'show']);