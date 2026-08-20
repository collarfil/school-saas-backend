<?php

use Illuminate\Support\Facades\Route;
use App\Modules\CBT\Controllers\Api\ExamTypeController;
use App\Modules\CBT\Controllers\Api\ExamController;
use App\Modules\CBT\Controllers\Api\QuestionController;
use App\Modules\CBT\Controllers\Api\OptionController;
use App\Modules\CBT\Controllers\Api\ExamGradeController;
use App\Modules\CBT\Controllers\Api\ExamSessionController;
use App\Modules\CBT\Controllers\Api\ExamResultController;
use App\Modules\CBT\Controllers\Api\StudentResponseController;

/*
|--------------------------------------------------------------------------
| PROTECTED CBT ROUTING SUITE (JWT ROUTE GUARD ACTIVE)
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth'])->prefix('v1/cbt')->group(function () {

    /*
    | EXAM BLUEPRINTS & PARAMETER SETUP CRUD
    */
    Route::apiResource('exam-types', ExamTypeController::class);
    Route::apiResource('exams', ExamController::class);
    
    /*
    | ASSIGNED TARGET CLASSROOM PIVOT SYNC
    */
    Route::get('exam-grades', [ExamGradeController::class, 'index']);
    Route::post('exam-grades/sync', [ExamGradeController::class, 'syncGrades']);

    /*
    | QUESTION BANKS MANAGEMENT SYSTEM
    */
    Route::apiResource('questions', QuestionController::class)->except(['index']);
    Route::get('exams/{exam_id}/questions', [QuestionController::class, 'index']);

    /*
    | OPTION ITEMS BANK INTERFACE
    */
    Route::apiResource('options', OptionController::class)->only(['store', 'update', 'destroy']);

    /*
    | RUNTIME ENGINE: MONITORING & LIVE SESSION ENGINE TRACKING
    */
    Route::prefix('session')->group(function () {
        Route::post('start', [ExamSessionController::class, 'startSession']);
        Route::post('progress/sync', [ExamSessionController::class, 'saveResponse']);
        Route::post('{id}/submit', [ExamSessionController::class, 'submitSession']);
        
        // Audit log tracking trace access lines endpoint
        Route::get('responses', [StudentResponseController::class, 'index']);
    });

    /*
    | PERFORMANCE & HISTORICAL METRICS REPORT GRIDS
    */
    Route::get('results', [ExamResultController::class, 'index']);
    Route::get('results/{id}', [ExamResultController::class, 'show']);
    Route::post('results/{id}/override-grading', [ExamResultController::class, 'gradeTheory']);
});