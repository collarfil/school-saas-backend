<?php

use Illuminate\Support\Facades\Route;

use App\Modules\Finance\Controllers\Api\FeeController;
use App\Modules\Finance\Controllers\Api\FeePaymentController;
use App\Modules\Finance\Controllers\Api\TransactionController;
use App\Modules\Finance\Controllers\Api\IncomeController;
use App\Modules\Finance\Controllers\Api\ExpenseController;
use App\Modules\Finance\Controllers\Api\FinanceController;

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
        Route::get('/stats', [TransactionController::class, 'getStats']);
        Route::get('/fee-payments', [TransactionController::class, 'getFeePaymentTransactions']);
        Route::get('/{id}', [TransactionController::class, 'show']);
        Route::put('/{id}', [TransactionController::class, 'update']);
        Route::delete('/{id}', [TransactionController::class, 'destroy']);
    });
});