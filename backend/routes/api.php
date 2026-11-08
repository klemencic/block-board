<?php

use App\Http\Controllers\Api\ApartmentController;
use App\Http\Controllers\Api\BuildingChargeController;
use App\Http\Controllers\Api\ChargeController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\LeaseController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\SharedSpaceController;
use App\Http\Controllers\Api\UtilityBillController;
use Illuminate\Support\Facades\Route;

Route::post('/invitations/accept', [InvitationController::class, 'accept'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show']);
    Route::get('/me/leases', [LeaseController::class, 'mine']);
    Route::get('/me/charges', [ChargeController::class, 'mine']);

    Route::get('/buildings/{building}/charges', [BuildingChargeController::class, 'index']);
    Route::get('/buildings/{building}/charge-periods', [BuildingChargeController::class, 'periods']);
    Route::get('/buildings/{building}/lease-tenants', [BuildingChargeController::class, 'tenants']);
    Route::post('/buildings/{building}/charges', [BuildingChargeController::class, 'store']);
    Route::post('/charges/{charge}/mark-paid', [BuildingChargeController::class, 'markPaid']);

    Route::get('/buildings/{building}/shared-spaces', [SharedSpaceController::class, 'index']);
    Route::get('/shared-spaces/{space}/availability', [SharedSpaceController::class, 'availability']);
    Route::post('/shared-spaces/{space}/bookings', [SharedSpaceController::class, 'book']);

    Route::get('/buildings/{building}/utility-bills', [UtilityBillController::class, 'index']);
    Route::post('/buildings/{building}/utility-bills/preview', [UtilityBillController::class, 'preview']);
    Route::post('/buildings/{building}/utility-bills', [UtilityBillController::class, 'store']);

    Route::get('/charges/{charge}', [ChargeController::class, 'show']);
    Route::get('/charges/{charge}/source', [ChargeController::class, 'source']);

    Route::get('/buildings/{building}/apartments', [ApartmentController::class, 'index']);
    Route::get('/buildings/{building}/leases', [LeaseController::class, 'index']);

    Route::post('/apartments/{apartment}/leases', [LeaseController::class, 'store']);
    Route::get('/leases/{lease}', [LeaseController::class, 'show']);
    Route::post('/leases/{lease}/activate', [LeaseController::class, 'activate']);
    Route::post('/leases/{lease}/give-notice', [LeaseController::class, 'giveNotice']);
});
