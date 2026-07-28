<?php

use Illuminate\Support\Facades\Route;

use App\Modules\Academics\Controllers\Api\GradeController;
use App\Modules\Academics\Controllers\Api\SectionController;
use App\Modules\Academics\Controllers\Api\SchoolSessionController;
use App\Modules\Academics\Controllers\Api\SubjectController;
use App\Modules\Academics\Controllers\Api\ResultController;
use App\Modules\Academics\Controllers\Api\ResultLockController;
use App\Modules\Academics\Controllers\Api\AttendanceController;

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (JWT REQUIRED) - Academics
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth'])->prefix('v1')->group(function () {

    /*
    | ATTENDANCE
    */
    // Custom attendance endpoints (MUST be BEFORE apiResource to avoid route conflicts)
    Route::get('/attendances/available-grades', [AttendanceController::class, 'getAvailableGrades']);
    Route::get('/attendances/students-by-grade', [AttendanceController::class, 'getStudentsByGrade']);
    Route::post('/attendances/bulk', [AttendanceController::class, 'bulkStore']);
    
    // Standard CRUD routes
    Route::apiResource('attendances', AttendanceController::class);

    /*
    | SECTIONS
    */
    Route::apiResource('sections', SectionController::class);

    /*
    | SCHOOL SESSIONS
    */
    Route::apiResource('school-sessions', SchoolSessionController::class);

    /*
    | GRADES & SUBJECTS
    */
    Route::apiResource('grades', GradeController::class);
    Route::apiResource('subjects', SubjectController::class);

    /*
    | RESULTS
    */
    Route::prefix('results')->group(function () {
        Route::get('/', [ResultController::class, 'index']);
        Route::post('/', [ResultController::class, 'store']);
        Route::put('/', [ResultController::class, 'update']);
        Route::delete('/', [ResultController::class, 'destroy']);
        Route::get('/reports/student', [ResultController::class, 'studentReport']);
        Route::post('/publish', [ResultController::class, 'publishResults']);
    });

    /*
    | RESULT LOCK
    */
    Route::prefix('result-lock')->group(function () {
        Route::post('/lock', [ResultLockController::class, 'lock']);
        Route::post('/unlock', [ResultLockController::class, 'unlock']);
        Route::get('/status', [ResultLockController::class, 'status']);
    });
});