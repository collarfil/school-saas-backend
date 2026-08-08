<?php

use Illuminate\Support\Facades\Route;

// Ensure this matches the exact namespace declaration at the top of your LoginController.php file
// ✅ CORRECT:
use App\Modules\Core\Controllers\Api\AuthController;
use App\Modules\Core\Controllers\Api\RegisterController;
use App\Modules\Core\Controllers\Api\LoginController;
use App\Modules\Core\Controllers\Api\PasswordResetController;
use App\Modules\Core\Controllers\Api\SchoolController;
use App\Modules\Core\Controllers\Api\SubscriptionController;
use App\Modules\Core\Controllers\Api\SchoolDashboardController;
use App\Modules\Core\Controllers\Api\DashboardController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (NO AUTH) - Core
|--------------------------------------------------------------------------
| Final URL: /api/v1/auth/login
*/
Route::prefix('v1')->group(function () {

    // Super Admin bootstrap
    Route::post('/register/super-admin', [RegisterController::class, 'registerSuperAdmin']);
    Route::get('/register/check-super-admin', [RegisterController::class, 'checkSuperAdmin']);

    // Login
    Route::prefix('auth')->group(function () {
        Route::post('/login', [LoginController::class, 'login']);
    });

    // Password Reset
    Route::prefix('password')->group(function () {
        Route::post('/forgot', [PasswordResetController::class, 'sendResetLink']);
        Route::post('/reset', [PasswordResetController::class, 'resetPassword']);
        Route::post('/validate-token', [PasswordResetController::class, 'validateToken']);
    });

    // Public subscription data & Paystack Webhook / Verification
    Route::prefix('subscriptions')->group(function () {
        Route::get('/pricing', [SubscriptionController::class, 'getPricing']);
        Route::post('/webhook', [SubscriptionController::class, 'handlePaymentWebhook']);
        Route::post('/verify', [SubscriptionController::class, 'verifyPayment']);
        Route::get('/callback', [SubscriptionController::class, 'paymentCallback']);
    });
});

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (JWT REQUIRED) - Core
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth'])->prefix('v1')->group(function () {
    
    /*
    | AUTH
    */
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });

    /*
    | DASHBOARDS
    */
    Route::get('/school/dashboard', [SchoolDashboardController::class, 'index']);
    Route::get('/employee/dashboard', [SchoolDashboardController::class, 'teachingDashboard'])
        ->middleware('teaching_staff');
    Route::get('/account/dashboard', [SchoolDashboardController::class, 'accountDashboard'])
        ->middleware('account_staff');
    Route::get('/student/dashboard', [SchoolDashboardController::class, 'studentDashboard'])
        ->middleware('role:student');
    Route::get('/parent/dashboard', [SchoolDashboardController::class, 'parentDashboard'])
        ->middleware('role:parent');

    /*
    | SUBSCRIPTIONS (Protected)
    */
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index']);
        Route::get('/status', [SubscriptionController::class, 'checkStatus']);
        Route::post('/initialize', [SubscriptionController::class, 'initializePayment']);
        Route::post('/cancel', [SubscriptionController::class, 'cancelPendingPayment']);
        Route::get('/{id}', [SubscriptionController::class, 'show']);
        Route::get('/{id}/transactions', [SubscriptionController::class, 'getSubscriptionTransactions']);
    });
});

/*
|--------------------------------------------------------------------------
| SUPER ADMIN ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth', 'super_admin'])->prefix('v1')->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'index']);
    Route::apiResource('schools', SchoolController::class);
    Route::post('/admin/schools/{school}/unlock', [SchoolController::class, 'unlock']);
});