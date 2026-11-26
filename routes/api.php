<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CreditApplicationController;
use App\Http\Controllers\Api\PublicContractController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:15,1');

Route::prefix('public/contracts')->middleware('throttle:30,1')->group(function (): void {
    Route::get('/{token}', [PublicContractController::class, 'show']);
    Route::post('/{token}/sign', [PublicContractController::class, 'sign']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/applications', [CreditApplicationController::class, 'index']);
    Route::post('/applications', [CreditApplicationController::class, 'store']);
    Route::get('/applications/{application}', [CreditApplicationController::class, 'show']);
    Route::put('/applications/{application}', [CreditApplicationController::class, 'update']);

    Route::post('/applications/{application}/submit', [CreditApplicationController::class, 'submit']);
    Route::post('/applications/{application}/approve', [CreditApplicationController::class, 'approve']);
    Route::post('/applications/{application}/reject', [CreditApplicationController::class, 'reject']);
    Route::get('/applications/{application}/contract', [CreditApplicationController::class, 'contract']);
    Route::get('/applications/{application}/po', [CreditApplicationController::class, 'po']);
    Route::post('/applications/{application}/send-po', [CreditApplicationController::class, 'sendPo']);
    Route::post('/applications/{application}/sign', [CreditApplicationController::class, 'sign']);
    Route::post('/applications/{application}/disburse', [CreditApplicationController::class, 'disburse']);
});

