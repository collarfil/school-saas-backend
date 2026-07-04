<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    /**
     * Get grades accessible to the authenticated user
     */
    private function getAccessibleGrades($schoolId)
    {
        $user = auth()->user();
        
        if ($user->isSuperAdmin() || $user->isSchoolAdmin()) {
            // Admin can see all grades
            return Grade::where('school_id', $schoolId)->pluck('id')->toArray();
        } elseif ($user->isEmployee()) {
            // Employee only sees grades assigned to them
            return DB::table('employee_grade')
                ->where('employee_id', $user->id)
                ->where('school_id', $schoolId)
                ->pluck('grade_id')
                ->toArray();
        }
        
        return [];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $schoolId = $request->school_id;
        $accessibleGradeIds = $this->getAccessibleGrades($schoolId);
        $user = auth()->user();

        // Build query
        $query = Attendance::where('school_id', $schoolId)
            ->with(['grade', 'student', 'schoolSession', 'school']);

        // Filter by accessible grades for employees
        if ($user->isEmployee() && !empty($accessibleGradeIds)) {
            $query->whereIn('grade_id', $accessibleGradeIds);
        }

        // Apply additional filters
        if ($request->has('grade_id') && $request->grade_id) {
            // Verify user has access to this grade
            if ($user->isEmployee() && !in_array($request->grade_id, $accessibleGradeIds)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this grade'
                ], 403);
            }
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->has('attendance_date') && $request->attendance_date) {
            $query->whereDate('attendance_date', $request->attendance_date);
        }

        $attendances = $query->orderBy('attendance_date', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $attendances
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
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $schoolId = $request->school_id;
        $accessibleGradeIds = $this->getAccessibleGrades($schoolId);
        $user = auth()->user();

        $grades = Grade::where('school_id', $schoolId);

        if ($user->isEmployee() && !empty($accessibleGradeIds)) {
            $grades->whereIn('id', $accessibleGradeIds);
        }

        $grades = $grades->orderBy('name')->get();

        return response()->json([
            'status' => 'success',
            'data' => $grades
        ]);
    }

    /**
     * Get students for a specific grade with access control
     */
    public function getStudentsByGrade(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'grade_id' => 'required|exists:grades,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $gradeId = $request->grade_id;
        $schoolId = $request->school_id;
        $accessibleGradeIds = $this->getAccessibleGrades($schoolId);
        $user = auth()->user();

        // Verify access to this grade
        if ($user->isEmployee() && !in_array($gradeId, $accessibleGradeIds)) {
            return response()->json([
                'status' => 'error',
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
            'data' => $students
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'grade_id' => 'required|exists:grades,id',
            'student_id' => 'required|exists:students,id',
            'school_session_id' => 'required|exists:school_sessions,id',
            'attendance_date' => 'required|date',
            'is_present' => 'nullable|boolean',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $gradeId = $request->grade_id;
        $schoolId = $request->school_id;
        $accessibleGradeIds = $this->getAccessibleGrades($schoolId);
        $user = auth()->user();

        // Verify access to this grade
        if ($user->isEmployee() && !in_array($gradeId, $accessibleGradeIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this grade'
            ], 403);
        }

        // Check if attendance already exists for this student on this date
        $existingAttendance = Attendance::where('student_id', $request->student_id)
            ->whereDate('attendance_date', $request->attendance_date)
            ->first();

        if ($existingAttendance) {
            $existingAttendance->update($validator->validated());
            $attendance = $existingAttendance;
        } else {
            $attendance = Attendance::create($validator->validated());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance recorded successfully.',
            'data' => $attendance->load(['grade', 'student', 'schoolSession', 'school'])
        ], 201);
    }

    /**
     * Bulk store attendance records
     */
    public function bulkStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'records' => 'required|array',
            'records.*.student_id' => 'required|exists:students,id',
            'records.*.grade_id' => 'required|exists:grades,id',
            'records.*.school_session_id' => 'nullable|exists:school_sessions,id',
            'records.*.attendance_date' => 'required|date',
            'records.*.is_present' => 'required|boolean',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $schoolId = $request->school_id;
        $accessibleGradeIds = $this->getAccessibleGrades($schoolId);
        $user = auth()->user();
        $records = $request->records;

        // Get unique grade IDs from records
        $gradeIds = array_unique(array_column($records, 'grade_id'));

        // Verify access to all grades
        if ($user->isEmployee()) {
            $unauthorizedGrades = array_diff($gradeIds, $accessibleGradeIds);
            if (!empty($unauthorizedGrades)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to one or more grades'
                ], 403);
            }
        }

        $savedRecords = [];

        foreach ($records as $record) {
            $existingAttendance = Attendance::where('student_id', $record['student_id'])
                ->whereDate('attendance_date', $record['attendance_date'])
                ->first();

            $record['school_id'] = $schoolId;

            if ($existingAttendance) {
                $existingAttendance->update($record);
                $savedRecords[] = $existingAttendance;
            } else {
                $attendance = Attendance::create($record);
                $savedRecords[] = $attendance;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => count($savedRecords) . ' attendance records saved successfully',
            'data' => $savedRecords
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        $validator = Validator::make(['id' => $id, 'school_id' => $request->school_id], [
            'id' => 'required|exists:attendances,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $attendance = Attendance::where('school_id', $request->school_id)
            ->with(['grade', 'student', 'schoolSession', 'school'])
            ->find($id);

        if (!$attendance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Attendance record not found'
            ], 404);
        }

        $accessibleGradeIds = $this->getAccessibleGrades($request->school_id);
        $user = auth()->user();

        if ($user->isEmployee() && !in_array($attendance->grade_id, $accessibleGradeIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this record'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => $attendance
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:attendances,id',
            'grade_id' => 'sometimes|required|exists:grades,id',
            'student_id' => 'sometimes|required|exists:students,id',
            'school_session_id' => 'sometimes|required|exists:school_sessions,id',
            'attendance_date' => 'sometimes|required|date',
            'is_present' => 'nullable|boolean',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $attendance = Attendance::where('school_id', $request->school_id)->find($id);

        if (!$attendance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Attendance record not found'
            ], 404);
        }

        $accessibleGradeIds = $this->getAccessibleGrades($request->school_id);
        $user = auth()->user();

        if ($user->isEmployee() && !in_array($attendance->grade_id, $accessibleGradeIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this record'
            ], 403);
        }

        $attendance->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance updated successfully.',
            'data' => $attendance->fresh(['grade', 'student', 'schoolSession', 'school'])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $validator = Validator::make(['id' => $id, 'school_id' => $request->school_id], [
            'id' => 'required|exists:attendances,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $attendance = Attendance::where('school_id', $request->school_id)->find($id);

        if (!$attendance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Attendance record not found'
            ], 404);
        }

        $accessibleGradeIds = $this->getAccessibleGrades($request->school_id);
        $user = auth()->user();

        if ($user->isEmployee() && !in_array($attendance->grade_id, $accessibleGradeIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this record'
            ], 403);
        }

        $attendance->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance deleted successfully.'
        ]);
    }
}