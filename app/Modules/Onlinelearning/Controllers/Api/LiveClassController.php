<?php

namespace App\Modules\Onlinelearning\Controllers\Api;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Modules\Onlinelearning\Models\LiveClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LiveClassController extends Controller
{
    public function index(Request $request)
    {
        try {
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

            $query = LiveClass::where('school_id', $request->school_id)
                ->with(['grade', 'employee', 'subject', 'schoolSession']);

            // Filters
            if ($request->has('grade_id') && $request->grade_id) {
                $query->where('grade_id', $request->grade_id);
            }

            if ($request->has('employee_id') && $request->employee_id) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->has('subject_id') && $request->subject_id) {
                $query->where('subject_id', $request->subject_id);
            }

            if ($request->has('school_session_id') && $request->school_session_id) {
                $query->where('school_session_id', $request->school_session_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $liveClasses = $query->orderBy('created_at', 'desc')->paginate(25);

            return response()->json([
                'status' => 'success',
                'data' => $liveClasses
            ]);

        } catch (\Exception $e) {
            Log::error('LiveClass index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch live classes'
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
                'id' => 'required|exists:live_classes,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $liveClass = LiveClass::where('school_id', $request->school_id)
                ->with([
                    'grade', 
                    'employee', 
                    'subject', 
                    'schoolSession',
                    'meetings',
                    'assignments'
                ])
                ->find($id);

            if (!$liveClass) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Live class not found or does not belong to this school'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $liveClass
            ]);

        } catch (\Exception $e) {
            Log::error('LiveClass show error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred'
            ], 500);
        }
    }

 public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'school_id' => 'required|exists:schools,id',
        'grade_id' => 'required|exists:grades,id',
        'employee_id' => 'required|exists:employees,id',
        'subject_id' => 'required|exists:subjects,id',
        'school_session_id' => 'required|exists:school_sessions,id',
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'meeting_provider' => 'required|string|max:255',
        'meeting_url' => 'required|string',
        'meeting_id' => 'nullable|string|max:255',
        'meeting_code' => 'nullable|string|max:255',
        'meeting_password' => 'nullable|string|max:255',
        'start_time' => 'required|date',
        'end_time' => 'required|date|after:start_time',
        'schedule_date' => 'nullable|date', // <-- Added validation rule
        'status' => 'nullable|in:scheduled,ongoing,completed,cancelled',
        'recurring' => 'nullable|boolean',
        'recurrence_pattern' => 'nullable|string|max:255',
        'max_participants' => 'nullable|integer|min:0'
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

        $startTime = Carbon::parse($request->start_time);
        
        // Derive schedule_date from start_time if not provided explicitly
        $scheduleDate = $request->schedule_date 
            ? Carbon::parse($request->schedule_date) 
            : $startTime->toDateString();

        $meetingCode = $request->meeting_code 
            ?? 'CLASS-' . strtoupper(Str::random(8));

    $liveClass = LiveClass::create([
    'school_id' => $request->school_id,
    'grade_id' => $request->grade_id,
    'employee_id' => $request->employee_id,
    'subject_id' => $request->subject_id,
    'school_session_id' => $request->school_session_id,
    'title' => $request->title,
    'description' => $request->description,
    'meeting_provider' => $request->meeting_provider,
    'meeting_url' => $request->meeting_url,
    'meeting_id' => $request->meeting_id,
    'meeting_code' => $meetingCode,
    'meeting_password' => $request->meeting_password,
    'schedule_date' => $scheduleDate,
    'start_time' => $startTime,
    'end_time' => Carbon::parse($request->end_time),
    'status' => $request->status ?? 'scheduled',
    'recurring' => $request->recurring ?? false,
    'recurrence_pattern' => $request->recurrence_pattern,
    'max_participants' => $request->max_participants ?? 0,
    'is_recorded' => $request->is_recorded ?? false,
    'allow_chat' => $request->allow_chat ?? true, // <-- Defaulting allow_chat
]);

        DB::commit();

        return response()->json([
            'status' => 'success',
            'message' => 'Live class created successfully',
            'data' => $liveClass->load(['grade', 'employee', 'subject', 'schoolSession'])
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('LiveClass store error: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to create live class: ' . $e->getMessage()
        ], 500);
    }
}

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:live_classes,id',
            'school_id' => 'required|exists:schools,id',
            'grade_id' => 'sometimes|exists:grades,id',
            'employee_id' => 'sometimes|exists:employees,id',
            'subject_id' => 'sometimes|exists:subjects,id',
            'school_session_id' => 'sometimes|exists:school_sessions,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'meeting_provider' => 'sometimes|string|max:255',
            'meeting_url' => 'sometimes|string',
            'meeting_id' => 'nullable|string|max:255',
            'meeting_password' => 'nullable|string|max:255',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'status' => 'nullable|in:scheduled,ongoing,completed,cancelled',
            'recurring' => 'nullable|boolean',
            'recurrence_pattern' => 'nullable|string|max:255',
            'max_participants' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $liveClass = LiveClass::where('school_id', $request->school_id)->find($id);

            if (!$liveClass) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Live class not found or does not belong to this school'
                ], 404);
            }

            $data = $request->except(['school_id']);

            // Parse dates if provided
            if ($request->has('start_time')) {
                $data['start_time'] = Carbon::parse($request->start_time);
            }

            if ($request->has('end_time')) {
                $data['end_time'] = Carbon::parse($request->end_time);
            }

            $liveClass->update($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Live class updated successfully',
                'data' => $liveClass->fresh(['grade', 'employee', 'subject', 'schoolSession'])
            ]);

        } catch (\Exception $e) {
            Log::error('LiveClass update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update live class'
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
                'id' => 'required|exists:live_classes,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $liveClass = LiveClass::where('school_id', $request->school_id)->find($id);

            if (!$liveClass) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Live class not found or does not belong to this school'
                ], 404);
            }

            $liveClass->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Live class deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('LiveClass destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete live class'
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:live_classes,id',
            'school_id' => 'required|exists:schools,id',
            'status' => 'required|in:scheduled,ongoing,completed,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $liveClass = LiveClass::where('school_id', $request->school_id)->find($id);

            if (!$liveClass) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Live class not found or does not belong to this school'
                ], 404);
            }

            $liveClass->update(['status' => $request->status]);

            return response()->json([
                'status' => 'success',
                'message' => 'Live class status updated successfully',
                'data' => $liveClass
            ]);

        } catch (\Exception $e) {
            Log::error('LiveClass updateStatus error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update live class status'
            ], 500);
        }
    }

    public function getUpcoming(Request $request)
    {
        try {
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

            $now = Carbon::now();

            $liveClasses = LiveClass::where('school_id', $request->school_id)
                ->where('start_time', '>', $now)
                ->where('status', 'scheduled')
                ->with(['grade', 'employee', 'subject', 'schoolSession'])
                ->orderBy('start_time', 'asc')
                ->limit(10)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $liveClasses
            ]);

        } catch (\Exception $e) {
            Log::error('LiveClass getUpcoming error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch upcoming live classes'
            ], 500);
        }
    }

    public function getOngoing(Request $request)
    {
        try {
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

            $now = Carbon::now();

            $liveClasses = LiveClass::where('school_id', $request->school_id)
                ->where('start_time', '<=', $now)
                ->where('end_time', '>=', $now)
                ->where('status', 'ongoing')
                ->with(['grade', 'employee', 'subject', 'schoolSession'])
                ->orderBy('start_time', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $liveClasses
            ]);

        } catch (\Exception $e) {
            Log::error('LiveClass getOngoing error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch ongoing live classes'
            ], 500);
        }
    }

    public function getByDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();

            $liveClasses = LiveClass::where('school_id', $request->school_id)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->with(['grade', 'employee', 'subject', 'schoolSession'])
                ->orderBy('start_time', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $liveClasses
            ]);

        } catch (\Exception $e) {
            Log::error('LiveClass getByDateRange error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch live classes by date range'
            ], 500);
        }
    }

    public function getTeacherLiveClasses(Request $request, $employeeId)
    {
        try {
            $validator = Validator::make([
                'employee_id' => $employeeId,
                'school_id' => $request->school_id
            ], [
                'employee_id' => 'required|exists:employees,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $liveClasses = LiveClass::where('school_id', $request->school_id)
                ->where('employee_id', $employeeId)
                ->with(['grade', 'subject', 'schoolSession'])
                ->orderBy('start_time', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $liveClasses
            ]);

        } catch (\Exception $e) {
            Log::error('LiveClass getTeacherLiveClasses error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch teacher live classes'
            ], 500);
        }
    }

    public function getGradeLiveClasses(Request $request, $gradeId)
    {
        try {
            $validator = Validator::make([
                'grade_id' => $gradeId,
                'school_id' => $request->school_id
            ], [
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

            $liveClasses = LiveClass::where('school_id', $request->school_id)
                ->where('grade_id', $gradeId)
                ->with(['employee', 'subject', 'schoolSession'])
                ->orderBy('start_time', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $liveClasses
            ]);

        } catch (\Exception $e) {
            Log::error('LiveClass getGradeLiveClasses error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch grade live classes'
            ], 500);
        }
    }

    public function getStats(Request $request)
    {
        try {
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

            $now = Carbon::now();

            $stats = [
                'total' => LiveClass::where('school_id', $request->school_id)->count(),
                'scheduled' => LiveClass::where('school_id', $request->school_id)
                    ->where('status', 'scheduled')
                    ->where('start_time', '>', $now)
                    ->count(),
                'ongoing' => LiveClass::where('school_id', $request->school_id)
                    ->where('status', 'ongoing')
                    ->where('start_time', '<=', $now)
                    ->where('end_time', '>=', $now)
                    ->count(),
                'completed' => LiveClass::where('school_id', $request->school_id)
                    ->where('status', 'completed')
                    ->count(),
                'cancelled' => LiveClass::where('school_id', $request->school_id)
                    ->where('status', 'cancelled')
                    ->count(),
                'upcoming_this_week' => LiveClass::where('school_id', $request->school_id)
                    ->where('status', 'scheduled')
                    ->whereBetween('start_time', [$now, $now->copy()->endOfWeek()])
                    ->count()
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('LiveClass getStats error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch live class statistics'
            ], 500);
        }
    }
}