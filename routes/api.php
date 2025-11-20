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

});