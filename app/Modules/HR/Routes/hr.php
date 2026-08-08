<?php



use Illuminate\Support\Facades\Route;

use App\Modules\HR\Controllers\Api\EmployeeController;
use App\Modules\HR\Controllers\Api\EmployeeGradeController;
use App\Modules\HR\Controllers\Api\EmployeeSubjectController;
use App\Modules\HR\Controllers\Api\StudentController;
use App\Modules\HR\Controllers\Api\ParentController;

// 🔐 Module Cross-References
use App\Modules\Academics\Controllers\Api\ResultController;
use App\Modules\Academics\Controllers\Api\AttendanceController; // <-- Added
use App\Modules\Academics\Controllers\Api\SubjectController;    // <-- Added
use App\Modules\Finance\Controllers\Api\FeePaymentController;    // <-- Added (or your Finance path)

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (JWT REQUIRED) - HR
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth'])->prefix('v1')->group(function () {

    /*
    | EMPLOYEE DASHBOARD (Self Context)
    */
    Route::prefix('employee')->group(function () {
        Route::get('/dashboard/stats', [EmployeeController::class, 'dashboardStats']);
        Route::get('/dashboard/classes', [EmployeeController::class, 'myClasses']);
        Route::get('/dashboard/pending-results', [ResultController::class, 'pendingGrading']);
    });

    /*
    | STUDENT DASHBOARD (Self Context)
    */
    Route::prefix('student')->group(function () {
        Route::get('/dashboard/stats', [StudentController::class, 'dashboardStats']);
        Route::get('/dashboard/attendance', [AttendanceController::class, 'myAttendance']);
        Route::get('/dashboard/results', [ResultController::class, 'myGrades']);
        Route::get('/dashboard/subjects', [SubjectController::class, 'mySubjects']);
        Route::get('/dashboard/fees', [FeePaymentController::class, 'myBalance']);
    });

    /*
    | PARENT DASHBOARD (Self Context)
    */
    Route::prefix('parent')->group(function () {
        Route::get('/dashboard/stats', [ParentController::class, 'dashboardStats']);
        Route::get('/dashboard/children', [StudentController::class, 'myChildren']);
        Route::get('/dashboard/attendance', [AttendanceController::class, 'childrenAttendance']);
        Route::get('/dashboard/fees', [FeePaymentController::class, 'childrenBalance']);
    });

    /*
    | STUDENTS CRUD
    */
    Route::apiResource('students', StudentController::class);

    /*
    | PARENTS CRUD
    */
    Route::apiResource('parents', ParentController::class);

    /*
    | EMPLOYEES CRUD
    */
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('employee-grades', EmployeeGradeController::class);
    Route::apiResource('employee-subjects', EmployeeSubjectController::class);
});