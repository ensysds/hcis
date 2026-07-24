<?php

use App\Http\Controllers\Api\CoreAuthController;
use App\Http\Controllers\Api\CoreWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('core/v1')->group(function () {
    Route::post('/auth/login', [CoreAuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('core.auth')->group(function () {
        Route::get('/auth/session', [CoreAuthController::class, 'session']);
        Route::post('/auth/logout', [CoreAuthController::class, 'logout']);
        Route::get('/me/bootstrap', [CoreWorkspaceController::class, 'bootstrap']);
        Route::get('/me/attendance', [CoreWorkspaceController::class, 'attendance']);
        Route::post('/me/attendance/check-in', [CoreWorkspaceController::class, 'checkIn'])->middleware('throttle:10,1');
        Route::post('/me/attendance/check-out', [CoreWorkspaceController::class, 'checkOut'])->middleware('throttle:10,1');
        Route::get('/me/leave/requests', [CoreWorkspaceController::class, 'leaveRequests']);
        Route::post('/me/leave/requests', [CoreWorkspaceController::class, 'storeLeaveRequest'])->middleware('throttle:30,1');
        Route::get('/me/overtime/requests', [CoreWorkspaceController::class, 'overtimeRequests']);
        Route::post('/me/overtime/requests', [CoreWorkspaceController::class, 'storeOvertimeRequest'])->middleware('throttle:30,1');
        Route::get('/me/modules/{module}/records', [CoreWorkspaceController::class, 'moduleRecords']);
        Route::post('/me/modules/{module}/records', [CoreWorkspaceController::class, 'storeModuleRecord'])->middleware('throttle:30,1');
    });
});
