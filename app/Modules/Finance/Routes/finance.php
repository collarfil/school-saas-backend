<?php

use Illuminate\Support\Facades\Route;

use App\Modules\Finance\Controllers\Api\FeeController;
use App\Modules\Finance\Controllers\Api\FeePaymentController;
use App\Modules\Finance\Controllers\Api\TransactionController;
use App\Modules\Finance\Controllers\Api\IncomeController;
use App\Modules\Finance\Controllers\Api\ExpenseController;
use App\Modules\Finance\Controllers\Api\FinanceController;
use App\Modules\Finance\Controllers\Api\SchoolGatewayController;
use App\Modules\Finance\Controllers\Api\ParentPaymentController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (NO JWT REQUIRED) - Webhooks & Gateway Callbacks
|--------------------------------------------------------------------------
*/
Route::prefix('v1/finance/webhooks')->group(function () {
    Route::post('/{provider}/{schoolId}', [TransactionController::class, 'handleWebhook']);
});

// Payment callback route (public - no auth needed) - ADD THIS WITH NAME
Route::prefix('v1/payment')->group(function () {
    Route::get('/callback', [ParentPaymentController::class, 'paymentCallback'])->name('payment.callback');
    Route::post('/callback', [ParentPaymentController::class, 'paymentCallback'])->name('payment.callback.post');
});

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (JWT REQUIRED) - Finance
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth'])->prefix('v1')->group(function () {

    /*
    | FINANCE REPORT
    */
    Route::post('/finance/report', [FinanceController::class, 'financeReport']);

    /*
    | FEES
    */
    Route::apiResource('fees', FeeController::class);

    /*
    | INCOMES
    */
    Route::apiResource('incomes', IncomeController::class);

    /*
    | EXPENSES
    */
    Route::apiResource('expenses', ExpenseController::class);

    /*
    | FEE PAYMENTS
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
    | TRANSACTIONS
    */
    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);
        Route::post('/', [TransactionController::class, 'store']);
        Route::post('/online-initialize', [TransactionController::class, 'initializeOnlinePayment']);
        Route::get('/stats', [TransactionController::class, 'getStats']);
        Route::get('/fee-payments', [TransactionController::class, 'getFeePaymentTransactions']);
        Route::get('/{id}', [TransactionController::class, 'show']);
        Route::put('/{id}', [TransactionController::class, 'update']);
        Route::delete('/{id}', [TransactionController::class, 'destroy']);
    });

    /*
    | PAYMENT GATEWAY SETTINGS
    */
    Route::prefix('gateways')->group(function () {
        Route::get('/', [SchoolGatewayController::class, 'index']);
        Route::post('/', [SchoolGatewayController::class, 'store']);
    });

    /*
    | PARENT PAYMENT ROUTES
    */
    Route::prefix('parent')->group(function () {
        Route::get('/grades', [ParentPaymentController::class, 'getGrades']);
        Route::get('/sessions', [ParentPaymentController::class, 'getSessions']);
        Route::get('/students', [ParentPaymentController::class, 'getStudents']);
        Route::get('/fees-by-grade', [ParentPaymentController::class, 'getFeesByGrade']);
        Route::post('/initialize-payment', [ParentPaymentController::class, 'initializePayment']);
        Route::get('/verify-payment', [ParentPaymentController::class, 'verifyPayment']);
        Route::get('/payment-history', [ParentPaymentController::class, 'getPaymentHistory']);
        Route::get('/children-fees', [ParentPaymentController::class, 'getChildrenFeeStructures']);
    });
});