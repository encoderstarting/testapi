<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ContactController;
use Illuminate\Support\Facades\Route;

Route::post('/contact', ContactController::class)
    ->middleware([
        'log.contact',
        'throttle:5,1',
    ]);
