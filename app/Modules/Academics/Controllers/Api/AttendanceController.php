<?php

namespace App\Modules\Academics\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\Attendance;
use App\Modules\Academics\Models\Grade;
use App\Modules\HR\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    /**
     * Get grades accessible to the authenticated user scoped by school
     */
    private function getAccessibleGrades($schoolId)
    {
        $user = auth()->user();

        if (!$user) {
            return [];
        }

        // Safely check role methods or properties if defined
        $isSuperAdmin = method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : ($user->role === 'super_admin');
        $isSchoolAdmin = method_exists($user, 'isSchoolAdmin') ? $user->isSchoolAdmin() : ($user->role === 'school_admin');
        $isEmployee = method_exists($user, 'isEmployee') ? $user->isEmployee() : ($user->role === 'employee');

        if ($isSuperAdmin || $isSchoolAdmin) {
            return Grade::where('school_id', $schoolId)->pluck('id')->toArray();
        } 
        
        if ($isEmployee) {
            // Safely retrieve employee ID using optional operator or direct reference
            $employeeId = optional($user->employee)->id ?? $user->id;

            return DB::table('employee_grade')
                ->where('employee_id', $employeeId)
                ->where('school_id', $schoolId)
                ->pluck('grade_id')
                ->toArray();
        }

        // Fallback default: return all grades for the school to prevent unintended empty blocking
        return Grade::where('school_id', $schoolId)->pluck('id')->toArray();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id'       => 'required|exists:schools,id',
            'grade_id'        => 'nullable|integer',
            'attendance_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $schoolId = $request->school_id;
        $accessibleGradeIds = $this->getAccessibleGrades($schoolId);
        $user = auth()->user();
        $isEmployee = $user && method_exists($user, 'isEmployee') ? $user->isEmployee() : false;

        $query = Attendance::where('school_id', $schoolId)
            ->with(['grade', 'student', 'schoolSession', 'school']);

        // Filter by accessible grades for employees
        if ($isEmployee) {
            if (empty($accessibleGradeIds)) {
                return response()->json(['status' => 'success', 'data' => []]);
            }
            $query->whereIn('grade_id', $accessibleGradeIds);
        }

        // Apply additional filters
        if ($request->filled('grade_id')) {
            if ($isEmployee && !in_array($request->grade_id, $accessibleGradeIds)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'You do not have access to this grade'
                ], 403);
            }
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->filled('attendance_date')) {
            $query->whereDate('attendance_date', $request->attendance_date);
        }

        $attendances = $query->orderBy('attendance_date', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data'   => $attendances
        ]);
    }

    /**
     * Get available grades for the authenticated user
     */
    public function getAvailableGrades(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $schoolId = $request->school_id;
        $accessibleGradeIds = $this->getAccessibleGrades($schoolId);
        $user = auth()->user();
        $isEmployee = $user && method_exists($user, 'isEmployee') ? $user->isEmployee() : false;

        $gradesQuery = Grade::where('school_id', $schoolId);

        if ($isEmployee) {
            if (empty($accessibleGradeIds)) {
                return response()->json(['status' => 'success', 'data' => []]);
            }
            $gradesQuery->whereIn('id', $accessibleGradeIds);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $gradesQuery->orderBy('name')->get()
        ]);
    }

    /**
     * Get students for a specific grade with access control
     */
    public function getStudentsByGrade(Request $request)
    {
        $schoolId = $request->school_id;

        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'grade_id'  => [
                'required',
                Rule::exists('grades', 'id')->where('school_id', $schoolId)
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $gradeId = $request->grade_id;
        $accessibleGradeIds = $this->getAccessibleGrades($schoolId);
        $user = auth()->user();
        $isEmployee = $user && method_exists($user, 'isEmployee') ? $user->isEmployee() : false;

        if ($isEmployee && !in_array($gradeId, $accessibleGradeIds)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You do not have access to this grade'
            ], 403);
        }

        $students = Student::where('school_id', $schoolId)
            ->where('grade_id', $gradeId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $students
        ]);
    }

    /**
     * Store or update single attendance record
     */
    public function store(Request $request)
    {
        $schoolId = $request->school_id;

        $validator = Validator::make($request->all(), [
            'school_id'         => 'required|exists:schools,id',
            'grade_id'          => ['required', Rule::exists('grades', 'id')->where('school_id', $schoolId)],
            'student_id'        => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'school_session_id' => ['nullable', Rule::exists('school_sessions', 'id')->where('school_id', $schoolId)],
            'attendance_date'   => 'required|date',
            'is_present'        => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $user = auth()->user();
        $accessibleGradeIds = $this->getAccessibleGrades($schoolId);
        $isEmployee = $user && method_exists($user, 'isEmployee') ? $user->isEmployee() : false;

        if ($isEmployee && !in_array($validated['grade_id'], $accessibleGradeIds)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You do not have access to this grade'
            ], 403);
        }

        $attendance = Attendance::updateOrCreate(
            [
                'school_id'       => $schoolId,
                'student_id'      => $validated['student_id'],
                'attendance_date' => $validated['attendance_date'],
            ],
            $validated
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Attendance recorded successfully.',
            'data'    => $attendance->load(['grade', 'student', 'schoolSession', 'school'])
        ], 201);
    }

    /**
     * Bulk store attendance records
     */
    public function bulkStore(Request $request)
    {
        $schoolId = $request->school_id;

        $validator = Validator::make($request->all(), [
            'school_id'                   => 'required|exists:schools,id',
            'records'                     => 'required|array|min:1',
            'records.*.student_id'        => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'records.*.grade_id'          => ['required', Rule::exists('grades', 'id')->where('school_id', $schoolId)],
            'records.*.school_session_id' => ['nullable', Rule::exists('school_sessions', 'id')->where('school_id', $schoolId)],
            'records.*.attendance_date'   => 'required|date',
            'records.*.is_present'        => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();
        $user = auth()->user();
        $accessibleGradeIds = $this->getAccessibleGrades($schoolId);
        $isEmployee = $user && method_exists($user, 'isEmployee') ? $user->isEmployee() : false;

        $gradeIds = array_unique(array_column($validatedData['records'], 'grade_id'));

        if ($isEmployee) {
            $unauthorizedGrades = array_diff($gradeIds, $accessibleGradeIds);
            if (!empty($unauthorizedGrades)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'You do not have access to one or more selected grades'
                ], 403);
            }
        }

        $savedRecords = [];

        DB::beginTransaction();
        try {
            foreach ($validatedData['records'] as $record) {
                $record['school_id'] = $schoolId;

                $attendance = Attendance::updateOrCreate(
                    [
                        'school_id'       => $schoolId,
                        'student_id'      => $record['student_id'],
                        'attendance_date' => $record['attendance_date'],
                    ],
                    $record
                );

                $savedRecords[] = $attendance;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk attendance store failed: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to process attendance records.'
            ], 500);
        }

        return response()->json([
            'status'  => 'success',
            'message' => count($savedRecords) . ' attendance records saved successfully',
            'data'    => $savedRecords
        ], 201);
    }

    /**
     * Display specified attendance record
     */
    public function show(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $attendance = Attendance::where('school_id', $request->school_id)
            ->with(['grade', 'student', 'schoolSession', 'school'])
            ->find($id);

        if (!$attendance) {
            return response()->json(['status' => 'error', 'message' => 'Attendance record not found'], 404);
        }

        $accessibleGradeIds = $this->getAccessibleGrades($request->school_id);
        $user = auth()->user();
        $isEmployee = $user && method_exists($user, 'isEmployee') ? $user->isEmployee() : false;

        if ($isEmployee && !in_array($attendance->grade_id, $accessibleGradeIds)) {
            return response()->json(['status' => 'error', 'message' => 'You do not have access to this record'], 403);
        }

        return response()->json(['status' => 'success', 'data' => $attendance]);
    }

    /**
     * Update attendance record
     */
    public function update(Request $request, $id)
    {
        $schoolId = $request->school_id;

        $validator = Validator::make($request->all(), [
            'school_id'         => 'required|exists:schools,id',
            'grade_id'          => ['sometimes', 'required', Rule::exists('grades', 'id')->where('school_id', $schoolId)],
            'student_id'        => ['sometimes', 'required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'school_session_id' => ['nullable', Rule::exists('school_sessions', 'id')->where('school_id', $schoolId)],
            'attendance_date'   => 'sometimes|required|date',
            'is_present'        => 'sometimes|required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $attendance = Attendance::where('school_id', $schoolId)->find($id);

        if (!$attendance) {
            return response()->json(['status' => 'error', 'message' => 'Attendance record not found'], 404);
        }

        $accessibleGradeIds = $this->getAccessibleGrades($schoolId);
        $user = auth()->user();
        $isEmployee = $user && method_exists($user, 'isEmployee') ? $user->isEmployee() : false;

        if ($isEmployee && !in_array($attendance->grade_id, $accessibleGradeIds)) {
            return response()->json(['status' => 'error', 'message' => 'You do not have access to this record'], 403);
        }

        $attendance->update($validator->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Attendance updated successfully.',
            'data'    => $attendance->fresh(['grade', 'student', 'schoolSession', 'school'])
        ]);
    }

    /**
     * Remove attendance record
     */
    public function destroy(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $attendance = Attendance::where('school_id', $request->school_id)->find($id);

        if (!$attendance) {
            return response()->json(['status' => 'error', 'message' => 'Attendance record not found'], 404);
        }

        $accessibleGradeIds = $this->getAccessibleGrades($request->school_id);
        $user = auth()->user();
        $isEmployee = $user && method_exists($user, 'isEmployee') ? $user->isEmployee() : false;

        if ($isEmployee && !in_array($attendance->grade_id, $accessibleGradeIds)) {
            return response()->json(['status' => 'error', 'message' => 'You do not have access to this record'], 403);
        }

        $attendance->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Attendance deleted successfully.'
        ]);
    }
}