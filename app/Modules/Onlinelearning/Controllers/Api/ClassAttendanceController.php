<?php

namespace App\Modules\Onlinelearning\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Onlinelearning\Models\ClassAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ClassAttendanceController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'meeting_id' => 'required|exists:meetings,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $attendance = ClassAttendance::where('meeting_id', $request->meeting_id)
                ->whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['meeting', 'student'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $attendance
            ]);

        } catch (\Exception $e) {
            Log::error('ClassAttendance index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch attendance records'
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:meeting_attendances,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $attendance = ClassAttendance::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['meeting', 'student'])
                ->find($id);

            if (!$attendance) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Attendance record not found or does not belong to this school'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $attendance
            ]);

        } catch (\Exception $e) {
            Log::error('ClassAttendance show error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meeting_id' => 'required|exists:meetings,id',
            'student_id' => 'required|exists:users,id',
            'joined_at' => 'nullable|date',
            'left_at' => 'nullable|date|after:joined_at',
            'duration' => 'nullable|integer|min:0',
            'attendance_status' => 'nullable|in:present,absent,late,excused'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Check for existing attendance record
            $existing = ClassAttendance::where('meeting_id', $request->meeting_id)
                ->where('student_id', $request->student_id)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Attendance record already exists for this student in this meeting'
                ], 409);
            }

            $attendance = ClassAttendance::create([
                'meeting_id' => $request->meeting_id,
                'student_id' => $request->student_id,
                'joined_at' => $request->joined_at ? Carbon::parse($request->joined_at) : Carbon::now(),
                'left_at' => $request->left_at ? Carbon::parse($request->left_at) : null,
                'duration' => $request->duration ?? 0,
                'attendance_status' => $request->attendance_status ?? 'present'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Attendance recorded successfully',
                'data' => $attendance->load(['meeting', 'student'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ClassAttendance store error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to record attendance: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:class_attendances,id',
            'school_id' => 'required|exists:schools,id',
            'joined_at' => 'nullable|date',
            'left_at' => 'nullable|date|after:joined_at',
            'duration' => 'nullable|integer|min:0',
            'attendance_status' => 'nullable|in:present,absent,late,excused'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $attendance = MeetingAttendance::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$attendance) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Attendance record not found or does not belong to this school'
                ], 404);
            }

            $data = $request->only(['duration', 'attendance_status']);

            if ($request->has('joined_at')) {
                $data['joined_at'] = Carbon::parse($request->joined_at);
            }

            if ($request->has('left_at')) {
                $data['left_at'] = Carbon::parse($request->left_at);
            }

            $attendance->update($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Attendance updated successfully',
                'data' => $attendance->fresh(['meeting', 'student'])
            ]);

        } catch (\Exception $e) {
            Log::error('MeetingAttendance update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update attendance'
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:class_attendances,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $attendance = MeetingAttendance::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$attendance) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Attendance record not found or does not belong to this school'
                ], 404);
            }

            $attendance->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Attendance record deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('MeetingAttendance destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete attendance record'
            ], 500);
        }
    }

    public function markPresent(Request $request, $meetingId, $studentId)
    {
        try {
            $validator = Validator::make([
                'meeting_id' => $meetingId,
                'student_id' => $studentId,
                'school_id' => $request->school_id
            ], [
                'meeting_id' => 'required|exists:meetings,id',
                'student_id' => 'required|exists:users,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify meeting belongs to school
            $meeting = \App\Modules\Onlinelearning\Models\Meeting::where('id', $meetingId)
                ->where('school_id', $request->school_id)
                ->first();

            if (!$meeting) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Meeting not found or does not belong to this school'
                ], 404);
            }

            $attendance = MeetingAttendance::updateOrCreate(
                [
                    'meeting_id' => $meetingId,
                    'student_id' => $studentId
                ],
                [
                    'joined_at' => Carbon::now(),
                    'attendance_status' => 'present'
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Student marked as present',
                'data' => $attendance
            ]);

        } catch (\Exception $e) {
            Log::error('MeetingAttendance markPresent error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark attendance'
            ], 500);
        }
    }

    public function markAbsent(Request $request, $meetingId, $studentId)
    {
        try {
            $validator = Validator::make([
                'meeting_id' => $meetingId,
                'student_id' => $studentId,
                'school_id' => $request->school_id
            ], [
                'meeting_id' => 'required|exists:meetings,id',
                'student_id' => 'required|exists:users,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $attendance = MeetingAttendance::updateOrCreate(
                [
                    'meeting_id' => $meetingId,
                    'student_id' => $studentId
                ],
                [
                    'attendance_status' => 'absent'
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Student marked as absent',
                'data' => $attendance
            ]);

        } catch (\Exception $e) {
            Log::error('MeetingAttendance markAbsent error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark attendance'
            ], 500);
        }
    }

    public function getMeetingAttendanceSummary(Request $request, $meetingId)
    {
        try {
            $validator = Validator::make([
                'meeting_id' => $meetingId,
                'school_id' => $request->school_id
            ], [
                'meeting_id' => 'required|exists:meetings,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $summary = MeetingAttendance::where('meeting_id', $meetingId)
                ->selectRaw('attendance_status, COUNT(*) as count')
                ->groupBy('attendance_status')
                ->get();

            $total = $summary->sum('count');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'meeting_id' => $meetingId,
                    'total' => $total,
                    'summary' => $summary,
                    'present_count' => $summary->where('attendance_status', 'present')->sum('count'),
                    'absent_count' => $summary->where('attendance_status', 'absent')->sum('count'),
                    'late_count' => $summary->where('attendance_status', 'late')->sum('count'),
                    'excused_count' => $summary->where('attendance_status', 'excused')->sum('count')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('MeetingAttendance getMeetingAttendanceSummary error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get attendance summary'
            ], 500);
        }
    }
}