<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;

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

});