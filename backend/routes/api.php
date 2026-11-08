<?php

use App\Http\Controllers\Api\ApartmentController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\LeaseController;
use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;

Route::post('/invitations/accept', [InvitationController::class, 'accept'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show']);
    Route::get('/me/leases', [LeaseController::class, 'mine']);

    Route::get('/buildings/{building}/apartments', [ApartmentController::class, 'index']);
    Route::get('/buildings/{building}/leases', [LeaseController::class, 'index']);

    Route::post('/apartments/{apartment}/leases', [LeaseController::class, 'store']);
    Route::get('/leases/{lease}', [LeaseController::class, 'show']);
    Route::post('/leases/{lease}/activate', [LeaseController::class, 'activate']);
    Route::post('/leases/{lease}/give-notice', [LeaseController::class, 'giveNotice']);
});
