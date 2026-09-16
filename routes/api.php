<?php

declare(strict_types=1);

use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\ShippingQuoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Supreme Steroids
|--------------------------------------------------------------------------
*/

Route::get('/health', [HealthCheckController::class, 'check']);
Route::get('/shipping/quote', [ShippingQuoteController::class, 'quote']);
