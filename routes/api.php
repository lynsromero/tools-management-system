<?php

use App\Http\Controllers\Api\Admin\AnalyticsController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\ToolController;
use App\Http\Controllers\Api\Admin\ToolFileController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DemoController;
use App\Http\Controllers\Api\DownloadController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
Route::post('/reset-password', [PasswordResetController::class, 'reset']);

Route::post('/stripe/webhook', StripeWebhookController::class);

Route::get('/tools', [StoreController::class, 'index']);

Route::post('/license/validate', [LicenseController::class, 'validateRequest'])->middleware('throttle:60,1');
Route::post('/license/activate', [LicenseController::class, 'activate'])->middleware('throttle:20,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me', [ProfileController::class, 'update']);
    Route::post('/me/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::get('/me/avatar', [ProfileController::class, 'avatar']);
    Route::delete('/me/avatar', [ProfileController::class, 'deleteAvatar']);
    Route::put('/me/password', [ProfileController::class, 'changePassword']);
    Route::get('/me/credits', [ReferralController::class, 'credits']);

    Route::post('/tools/{tool}/checkout', [StripeController::class, 'checkout']);
    Route::get('/tools/{tool}/demo', [DemoController::class, 'show']);
    Route::get('/tools/{tool}/download', [DownloadController::class, 'download'])->middleware('throttle:10,10');
    Route::get('/tools/{tool}/download/config', [DownloadController::class, 'config']);
    Route::get('/tools/{tool}/extension/{browser}', [DownloadController::class, 'extension'])
        ->where('browser', 'chrome|firefox|edge')
        ->middleware('throttle:10,10');

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

Route::middleware(['auth:sanctum', 'role:super_admin,admin'])
    ->prefix('admin')
    ->group(function () {
        Route::apiResource('tools', ToolController::class);
        Route::post('/tools/{tool}/files', [ToolFileController::class, 'store']);
        Route::delete('/tools/{tool}/files/{file}', [ToolFileController::class, 'destroy'])->scopeBindings();

        Route::get('/users', [UserController::class, 'index']);
        Route::patch('/users/{user}', [UserController::class, 'update']);

        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/analytics', [AnalyticsController::class, 'index']);
    });
