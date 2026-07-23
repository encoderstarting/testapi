<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DocumentationController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MetricsController;
use Illuminate\Support\Facades\Route;

Route::post('/contact', ContactController::class)
    ->middleware([
        'log.contact',
        'throttle:5,1',
    ]);

Route::get('/health', HealthController::class);
Route::get('/metrics', MetricsController::class);
Route::get('/documentation', [DocumentationController::class, 'index']);
Route::get('/openapi.json', [DocumentationController::class, 'openApi']);
