<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AssetCategoryController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AssetAssignmentController;
use App\Http\Controllers\Api\AssetTransferController;
use App\Http\Controllers\Api\AssetMaintenanceController;
use App\Http\Controllers\Api\AssetCheckoutController;
use App\Http\Controllers\Api\AuditLogController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    $user = $request->user();
    $user->role = $user->getRoleAttribute();
    return $user;
});

Route::post('/verify-invitation/{token}', [UserController::class, 'verifyInvitation']);
Route::post('/set-password', [UserController::class, 'setPassword']);

Route::middleware('auth:sanctum')->group(function () {

    // User Routes
    Route::delete('/users/{user}/force', [UserController::class, 'forceDelete']);
    Route::post('/users/{user}/restore', [UserController::class, 'restore']);
    Route::get('/users/trashed', [UserController::class, 'trashedUsers']);
    Route::get('/users/role/{role}', [UserController::class, 'getUsersByRole']);
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::post('/users/{user}/update-roles', [UserController::class, 'updateRoles']);
    Route::apiResource('/users', UserController::class);

    // Role Routes
    Route::apiResource('/roles', RoleController::class);

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/statistics', [DashboardController::class, 'statistics']);
        Route::get('/recent-activities', [DashboardController::class, 'recentActivities']);
        Route::get('/alerts', [DashboardController::class, 'alerts']);
    });

    // Assets
    Route::prefix('assets')->group(function () {
        Route::get('/', [AssetController::class, 'index']);
        Route::post('/', [AssetController::class, 'store']);
        Route::get('/statistics', [AssetController::class, 'statistics']);
        Route::get('/{id}', [AssetController::class, 'show']);
        Route::put('/{id}', [AssetController::class, 'update']);
        Route::delete('/{id}', [AssetController::class, 'destroy']);
        Route::get('/{id}/history', [AssetController::class, 'history']);
        Route::get('/{id}/qr-code', [AssetController::class, 'getQrCode']);
    });

    // Asset Categories
    Route::apiResource('asset-categories', AssetCategoryController::class);

    // Departments
    Route::apiResource('departments', DepartmentController::class);

    // Locations
    Route::apiResource('locations', LocationController::class);

    // Vendors
    Route::apiResource('vendors', VendorController::class);

    // Asset Assignments
    Route::prefix('asset-assignments')->group(function () {
        Route::get('/', [AssetAssignmentController::class, 'index']);
        Route::post('/', [AssetAssignmentController::class, 'store']);
        Route::get('/statistics', [AssetAssignmentController::class, 'statistics']);
        Route::get('/user/{userId}', [AssetAssignmentController::class, 'userAssignments']);
        Route::get('/{id}', [AssetAssignmentController::class, 'show']);
        Route::post('/{id}/return', [AssetAssignmentController::class, 'return']);
    });

    // Asset Transfers
    Route::prefix('asset-transfers')->group(function () {
        Route::get('/', [AssetTransferController::class, 'index']);
        Route::post('/', [AssetTransferController::class, 'store']);
        Route::get('/statistics', [AssetTransferController::class, 'statistics']);
        Route::get('/{id}', [AssetTransferController::class, 'show']);
        Route::post('/{id}/approve', [AssetTransferController::class, 'approve']);
        Route::post('/{id}/reject', [AssetTransferController::class, 'reject']);
        Route::post('/{id}/cancel', [AssetTransferController::class, 'cancel']);
    });

    // Asset Maintenance
    Route::prefix('asset-maintenance')->group(function () {
        Route::get('/', [AssetMaintenanceController::class, 'index']);
        Route::post('/', [AssetMaintenanceController::class, 'store']);
        Route::get('/statistics', [AssetMaintenanceController::class, 'statistics']);
        Route::get('/overdue', [AssetMaintenanceController::class, 'overdue']);
        Route::get('/upcoming', [AssetMaintenanceController::class, 'upcoming']);
        Route::get('/{id}', [AssetMaintenanceController::class, 'show']);
        Route::put('/{id}', [AssetMaintenanceController::class, 'update']);
        Route::post('/{id}/start', [AssetMaintenanceController::class, 'start']);
        Route::post('/{id}/complete', [AssetMaintenanceController::class, 'complete']);
        Route::post('/{id}/cancel', [AssetMaintenanceController::class, 'cancel']);
    });

    // Asset Checkouts
    Route::prefix('asset-checkouts')->group(function () {
        Route::get('/', [AssetCheckoutController::class, 'index']);
        Route::post('/', [AssetCheckoutController::class, 'store']);
        Route::get('/statistics', [AssetCheckoutController::class, 'statistics']);
        Route::get('/overdue', [AssetCheckoutController::class, 'overdue']);
        Route::get('/user/{userId}', [AssetCheckoutController::class, 'userCheckouts']);
        Route::get('/{id}', [AssetCheckoutController::class, 'show']);
        Route::post('/{id}/checkin', [AssetCheckoutController::class, 'checkin']);
        Route::post('/{id}/extend', [AssetCheckoutController::class, 'extend']);
        Route::post('/{id}/report-issue', [AssetCheckoutController::class, 'reportIssue']);
    });

    // Audit Logs
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/statistics', [AuditLogController::class, 'statistics']);
        Route::get('/recent', [AuditLogController::class, 'recentActivities']);
        Route::get('/search', [AuditLogController::class, 'search']);
        Route::get('/export', [AuditLogController::class, 'export']);
        Route::get('/user/{userId}', [AuditLogController::class, 'userLogs']);
        Route::get('/model/{modelType}/{modelId}', [AuditLogController::class, 'modelLogs']);
        Route::get('/timeline/{modelType}/{modelId}', [AuditLogController::class, 'timeline']);
        Route::get('/{id}', [AuditLogController::class, 'show']);
        Route::get('/{id}/compare', [AuditLogController::class, 'compareChanges']);
    });

});
