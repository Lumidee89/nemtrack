<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PickupController;
use App\Http\Controllers\Api\V1\VisitorController;
use App\Http\Controllers\Api\V1\MobilityController;
use App\Http\Controllers\Api\V1\PanicController;
use App\Http\Controllers\Api\V1\MobileDashboardController;
use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\MobileConfigController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/profile', fn (Request $request) => ['success' => true, 'message' => 'Profile retrieved.', 'data' => ['user' => $request->user()->load('organization')]]);
        Route::get('/modules', fn (Request $request) => ['success' => true, 'message' => 'Modules retrieved.', 'data' => $request->user()->role === 'super_admin' ? \App\Models\Module::where('available', true)->get() : ($request->user()->organization?->modules()->wherePivot('enabled', true)->where(fn ($query) => $query->whereNull('organization_modules.expires_at')->orWhere('organization_modules.expires_at', '>', now()))->get() ?? [])]);
        Route::get('/mobile/dashboard', MobileDashboardController::class);
        Route::get('/audit-logs', AuditController::class)->middleware('role:super_admin');
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/mobile/config', MobileConfigController::class);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);

        Route::middleware('module:VAS')->group(function () {
            Route::get('/visitors', [VisitorController::class, 'index'])->middleware('role:organization_admin,staff,resident');
            Route::get('/visitor-invitations', [VisitorController::class, 'invitations'])->middleware('role:organization_admin,staff,resident');
            Route::post('/visitor-invitations', [VisitorController::class, 'invite'])->middleware('role:organization_admin,staff,resident');
            Route::post('/visitor-access/verify', [VisitorController::class, 'verify'])->middleware(['role:organization_admin,security_officer', 'throttle:30,1']);
        });
        Route::middleware('module:PAS')->group(function () {
            Route::get('/students', [PickupController::class, 'students'])->middleware('role:organization_admin,security_officer,parent,guardian');
            Route::get('/pickup-persons', [PickupController::class, 'people'])->middleware('role:organization_admin,parent,guardian');
            Route::post('/pickup-authorizations', [PickupController::class, 'authorize'])->middleware('role:organization_admin,parent,guardian');
            Route::post('/pickup/verify', [PickupController::class, 'verify'])->middleware(['role:organization_admin,security_officer', 'throttle:30,1']);
        });
        Route::middleware('module:VTS')->group(function () {
            Route::get('/vehicles', [MobilityController::class, 'index'])->middleware('role:super_admin,organization_admin,security_officer,staff');
            Route::post('/vehicles', [MobilityController::class, 'store'])->middleware('role:organization_admin');
            Route::post('/vehicles/{vehicle}/telemetry', [MobilityController::class, 'telemetry'])->middleware(['role:organization_admin,security_officer', 'throttle:120,1']);
        });
        Route::middleware('module:PBS')->group(function () {
            Route::get('/panic-incidents', [PanicController::class, 'index'])->middleware('role:super_admin,organization_admin,security_officer');
            Route::post('/panic/trigger', [PanicController::class, 'trigger'])->middleware(['role:organization_admin,security_officer,staff,resident,parent,guardian', 'throttle:10,1']);
            Route::patch('/panic-incidents/{incident}', [PanicController::class, 'update'])->middleware('role:super_admin,organization_admin,security_officer');
        });
    });
});
