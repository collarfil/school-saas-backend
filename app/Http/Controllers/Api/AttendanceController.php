<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Validate school_id is provided
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

        // Fetch attendance records filtered by school_id with related data
        $attendances = Attendance::where('school_id', $request->school_id)
            ->with(['grade', 'student', 'schoolSession', 'school'])
            ->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $attendances
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Define validation rules
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

        $attendance = Attendance::create($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance recorded successfully.',
            'data' => $attendance->load(['grade', 'student', 'schoolSession', 'school'])
        ], 201);
    }

    /**
     * Display the specified resource.
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
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
                'message' => 'Attendance record not found or does not belong to this school.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $attendance
        ]);
    }

    /**
     * Update the specified resource in storage.
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
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
                'message' => 'Attendance record not found or does not belong to this school.'
            ], 404);
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
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
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
                'message' => 'Attendance record not found or does not belong to this school.'
            ], 404);
        }

        $attendance->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance deleted successfully.'
        ]);
    }
}