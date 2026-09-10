<?php
// App/Modules/Core/Routes/api.php (or your module's routes path)

use Illuminate\Support\Facades\Route;

use App\Modules\Core\Controllers\Api\AuthController;
use App\Modules\Core\Controllers\Api\RegisterController;
use App\Modules\Core\Controllers\Api\LoginController;
use App\Modules\Core\Controllers\Api\PasswordResetController;
use App\Modules\Core\Controllers\Api\SchoolController;
use App\Modules\Core\Controllers\Api\SubscriptionController;
use App\Modules\Core\Controllers\Api\SubscriptionAdminController;
use App\Modules\Core\Controllers\Api\SchoolDashboardController;
use App\Modules\Core\Controllers\Api\DashboardController;
use App\Modules\Core\Controllers\Api\AdmissionController;
use App\Modules\Core\Controllers\Api\AdmissionListController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (NO AUTH REQUIRED) - Core Module
|--------------------------------------------------------------------------
*/
Route::prefix('v1/public')->group(function () {
    // Central Public School Directory / Information
    Route::get('schools/search', [SchoolController::class, 'publicSearch']);
    Route::get('schools/{uuid}', [SchoolController::class, 'publicShowByUuid']);

    // Public Admission Portal Endpoints
    Route::prefix('admissions')->group(function () {
        Route::post('apply', [AdmissionController::class, 'publicApply']);
        Route::post('check-status', [AdmissionController::class, 'publicCheckStatus']);
    });
});

Route::prefix('v1')->group(function () {
    // Super Admin Bootstrap
    Route::post('register/super-admin', [RegisterController::class, 'registerSuperAdmin']);
    Route::get('register/check-super-admin', [RegisterController::class, 'checkSuperAdmin']);

    // Authentication
    Route::prefix('auth')->group(function () {
        Route::post('login', [LoginController::class, 'login']);
    });

    // Password Management
    Route::prefix('password')->group(function () {
        Route::post('forgot', [PasswordResetController::class, 'sendResetLink']);
        Route::post('reset', [PasswordResetController::class, 'resetPassword']);
        Route::post('validate-token', [PasswordResetController::class, 'validateToken']);
    });

    // Public Subscription & Webhook Processing
    Route::prefix('subscriptions')->group(function () {
        Route::get('pricing', [SubscriptionController::class, 'getPricing']);
        Route::get('pricing-options', [SubscriptionController::class, 'getPricing']);

        Route::post('webhook', [SubscriptionController::class, 'handlePaymentWebhook']);
        Route::post('verify', [SubscriptionController::class, 'verifyPayment']);
        Route::get('callback', [SubscriptionController::class, 'paymentCallback']);
    });
});

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (JWT REQUIRED) - Core Module
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth'])->prefix('v1')->group(function () {
    
    /*
    | AUTH USER OPERATIONS
    */
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });

    /*
    | DASHBOARDS
    */
    Route::get('school/dashboard', [SchoolDashboardController::class, 'index']);
    Route::get('employee/dashboard', [SchoolDashboardController::class, 'teachingDashboard'])
        ->middleware('teaching_staff');
    Route::get('account/dashboard', [SchoolDashboardController::class, 'accountDashboard'])
        ->middleware('account_staff');
    Route::get('student/dashboard', [SchoolDashboardController::class, 'studentDashboard'])
        ->middleware('role:student');
    Route::get('parent/dashboard', [SchoolDashboardController::class, 'parentDashboard'])
        ->middleware('role:parent');

    /*
    | SUBSCRIPTIONS (Protected)
    */
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index']);
        Route::get('status', [SubscriptionController::class, 'checkStatus']);
        Route::get('status/check', [SubscriptionController::class, 'checkStatus']);
        
        Route::post('initialize', [SubscriptionController::class, 'initializePayment']);
        Route::post('initialize-payment', [SubscriptionController::class, 'initializePayment']);
        
        Route::post('cancel', [SubscriptionController::class, 'cancelPendingPayment']);
        
        Route::get('{id}', [SubscriptionController::class, 'show']);
        Route::get('{id}/transactions', [SubscriptionController::class, 'getSubscriptionTransactions']);
    });

    /*
    | ADMISSIONS MANAGEMENT
    */
    // Main Applicants / Application CRUD (replaces basic route prefix)
    Route::apiResource('admissions', AdmissionController::class);

    // Admission Lists Management
    Route::prefix('admission-lists')->group(function () {
        Route::get('/', [AdmissionListController::class, 'index']);
        Route::post('/', [AdmissionListController::class, 'store']);
        Route::get('/{id}', [AdmissionListController::class, 'show']);
        Route::put('/{id}', [AdmissionListController::class, 'update']);
        Route::delete('/{id}', [AdmissionListController::class, 'destroy']);
        
        // Specialized Batch Operations
        Route::post('/{id}/publish', [AdmissionListController::class, 'publish']);
        Route::post('/{id}/sync-applicants', [AdmissionListController::class, 'syncApplicants']);
    });
});

/*
|--------------------------------------------------------------------------
| SUPER ADMIN ROUTES - Core Module
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth', 'super_admin'])->prefix('v1')->group(function () {
    Route::get('admin/dashboard', [DashboardController::class, 'index']);
    Route::apiResource('schools', SchoolController::class);
    
    Route::post('admin/schools/{school}/unlock', [SubscriptionAdminController::class, 'unlockSchool']);
    Route::post('admin/schools/{school}/lock', [SubscriptionAdminController::class, 'lockSchool']);
});