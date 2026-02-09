<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Controllers
|--------------------------------------------------------------------------
*/

// AUTH
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\LoginController;

// CORE
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\SchoolSessionController;
use App\Http\Controllers\Api\SubjectController;

// USERS
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeGradeController;
use App\Http\Controllers\Api\EmployeeSubjectController;

// FINANCE
use App\Http\Controllers\Api\FeeController;
use App\Http\Controllers\Api\FeePaymentController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SubscriptionPricingController;
use App\Http\Controllers\Api\IncomeController;
use App\Http\Controllers\Api\ExpenseController;

// ACADEMICS
use App\Http\Controllers\Api\ResultController;
use App\Http\Controllers\Api\ResultLockController;
use App\Http\Controllers\Api\AttendanceController;

// DASHBOARDS
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\SchoolDashboardController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (NO AUTH)
|--------------------------------------------------------------------------
*/

// Super Admin bootstrap
Route::post('/register/super-admin', [RegisterController::class, 'registerSuperAdmin']);
Route::get('/register/check-super-admin', [RegisterController::class, 'checkSuperAdmin']);

// Login
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
});

// Public subscription data
Route::prefix('v1')->group(function () {
    Route::get('/subscription-pricings/public', [SubscriptionPricingController::class, 'publicIndex']);
    Route::get('/subscriptions/payment/callback', [SubscriptionController::class, 'paymentCallback']);
    Route::post('/subscriptions/payment/webhook', [SubscriptionController::class, 'handlePaymentWebhook']);

    /*
    |--------------------------------------------------------------------------
    | 🔧 PATCH: PUBLIC SUBSCRIPTION STATUS (NO AUTH)
    |--------------------------------------------------------------------------
    | This is an alias ONLY.
    | Same controller, same logic, no behavior change.
    */
    Route::get(
        '/subscriptions/status/check',
        [SubscriptionController::class, 'checkStatus']
    );
});

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (JWT REQUIRED)
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth'])->prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | AUTH
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });

    /*
    |--------------------------------------------------------------------------
    | DASHBOARDS
    |--------------------------------------------------------------------------
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
    |--------------------------------------------------------------------------
    | STUDENTS
    |--------------------------------------------------------------------------
    */
    Route::apiResource('students', StudentController::class);

    /*
    |--------------------------------------------------------------------------
    | PARENTS
    |--------------------------------------------------------------------------
    */
    Route::apiResource('parents', ParentController::class);

    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE
    |--------------------------------------------------------------------------
    */
    Route::apiResource('attendances', AttendanceController::class);

    /*
    |--------------------------------------------------------------------------
    | SECTIONS
    |--------------------------------------------------------------------------
    */
    Route::apiResource('sections', SectionController::class);

    /*
    |--------------------------------------------------------------------------
    | SCHOOL SESSIONS
    |--------------------------------------------------------------------------
    */
    Route::apiResource('school-sessions', SchoolSessionController::class);

    /*
    |--------------------------------------------------------------------------
    | GRADES & SUBJECTS
    |--------------------------------------------------------------------------
    */
    Route::apiResource('grades', GradeController::class);
    Route::apiResource('subjects', SubjectController::class);

    /*
    |--------------------------------------------------------------------------
    | EMPLOYEES
    |--------------------------------------------------------------------------
    */
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('employee-grades', EmployeeGradeController::class);
    Route::apiResource('employee-subjects', EmployeeSubjectController::class);

    /*
    |--------------------------------------------------------------------------
    | FINANCE
    |--------------------------------------------------------------------------
    */
    Route::apiResource('fees', FeeController::class);
    Route::apiResource('incomes', IncomeController::class);
    Route::apiResource('expenses', ExpenseController::class);

    /*
    |--------------------------------------------------------------------------
    | FEE PAYMENTS
    |--------------------------------------------------------------------------
    */
    Route::prefix('fee-payments')->group(function () {
        Route::get('/', [FeePaymentController::class, 'index']);
        Route::post('/', [FeePaymentController::class, 'store']);
        Route::get('/student/{studentId}', [FeePaymentController::class, 'getStudentPayments']);
        Route::get('/stats', [FeePaymentController::class, 'getPaymentStats']);
        Route::get('/{id}', [FeePaymentController::class, 'show']);
        Route::put('/{id}', [FeePaymentController::class, 'update']);
        Route::delete('/{id}', [FeePaymentController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | TRANSACTIONS
    |--------------------------------------------------------------------------
    */
    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);
        Route::post('/', [TransactionController::class, 'store']);
        Route::get('/stats', [TransactionController::class, 'getStats']);
        Route::get('/fee-payments', [TransactionController::class, 'getFeePaymentTransactions']);
        Route::get('/{id}', [TransactionController::class, 'show']);
        Route::put('/{id}', [TransactionController::class, 'update']);
        Route::delete('/{id}', [TransactionController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | RESULTS
    |--------------------------------------------------------------------------
    */
    Route::prefix('results')->group(function () {
        Route::get('/', [ResultController::class, 'index']);
        Route::post('/', [ResultController::class, 'store']);
        Route::put('/', [ResultController::class, 'update']);
        Route::delete('/', [ResultController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | RESULT LOCK
    |--------------------------------------------------------------------------
    */
    Route::prefix('result-lock')->group(function () {
        Route::post('/lock', [ResultLockController::class, 'lock']);
        Route::post('/unlock', [ResultLockController::class, 'unlock']);
        Route::get('/status', [ResultLockController::class, 'status']);
    });

    /*
    |--------------------------------------------------------------------------
    | SUBSCRIPTIONS (PROTECTED)
    |--------------------------------------------------------------------------
    */
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index']);
        Route::get('/status/check', [SubscriptionController::class, 'checkStatus']);
        Route::get('/pricing-options', [SubscriptionController::class, 'getPricing']);
        Route::post('/initialize-payment', [SubscriptionController::class, 'initializePayment']);
        Route::post('/verify', [SubscriptionController::class, 'verifyPayment']);
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
});
