<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ApartmentController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\RepairActionController;
use App\Http\Controllers\Api\RepairRequestController;
use App\Http\Controllers\Api\BuildingChargeController;
use App\Http\Controllers\Api\BuildingOverviewController;
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

    Route::get('/buildings/{building}/repair-requests', [RepairRequestController::class, 'index']);
    Route::get('/buildings/{building}/repair-form', [RepairRequestController::class, 'form']);
    Route::post('/buildings/{building}/repair-requests', [RepairRequestController::class, 'store']);
    Route::get('/repair-requests/{repair}', [RepairRequestController::class, 'show']);
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show']);
    Route::get('/buildings/{building}/contractors', [RepairActionController::class, 'contractors']);
    Route::post('/repair-requests/{repair}/confirm', [RepairActionController::class, 'confirm']);
    Route::post('/repair-requests/{repair}/reject', [RepairActionController::class, 'reject']);
    Route::post('/repair-requests/{repair}/payer', [RepairActionController::class, 'payer']);
    Route::post('/repair-requests/{repair}/assign', [RepairActionController::class, 'assign']);
    Route::post('/repair-requests/{repair}/schedule', [RepairActionController::class, 'schedule']);
    Route::post('/repair-requests/{repair}/complete', [RepairActionController::class, 'complete']);
    Route::post('/repair-requests/{repair}/return', [RepairActionController::class, 'returnForRework']);
    Route::get('/repair-requests/{repair}/payer-candidates', [RepairActionController::class, 'payerCandidates']);
    Route::post('/repair-requests/{repair}/close', [RepairActionController::class, 'close']);

    Route::get('/buildings/{building}/announcements', [AnnouncementController::class, 'index']);
    Route::post('/buildings/{building}/announcements', [AnnouncementController::class, 'store']);
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show']);

    Route::get('/buildings/{building}/utility-bills', [UtilityBillController::class, 'index']);
    Route::post('/buildings/{building}/utility-bills/preview', [UtilityBillController::class, 'preview']);
    Route::post('/buildings/{building}/utility-bills', [UtilityBillController::class, 'store']);

    Route::get('/charges/{charge}', [ChargeController::class, 'show']);
    Route::get('/charges/{charge}/source', [ChargeController::class, 'source']);

    Route::get('/buildings/{building}/apartments', [ApartmentController::class, 'index']);
    Route::get('/buildings/{building}/overview', [BuildingOverviewController::class, 'show']);
    Route::get('/buildings/{building}/badges', [BuildingOverviewController::class, 'badges']);
    Route::get('/apartments/{apartment}', [BuildingOverviewController::class, 'apartment']);
    Route::get('/buildings/{building}/leases', [LeaseController::class, 'index']);

    Route::post('/apartments/{apartment}/leases', [LeaseController::class, 'store']);
    Route::get('/leases/{lease}', [LeaseController::class, 'show']);
    Route::post('/leases/{lease}/activate', [LeaseController::class, 'activate']);
    Route::post('/leases/{lease}/give-notice', [LeaseController::class, 'giveNotice']);
});
