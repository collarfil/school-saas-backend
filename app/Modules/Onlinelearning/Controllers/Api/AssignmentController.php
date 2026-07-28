<?php

namespace App\Modules\Onlinelearning\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Onlinelearning\Models\Assignment;
use App\Modules\Onlinelearning\Models\LiveClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'school_id' => 'required|exists:schools,id',
                'live_class_id' => 'nullable|exists:live_classes,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = Assignment::where('school_id', $request->school_id)
                ->with(['liveClass', 'subject', 'employee', 'submissions']);

            if ($request->has('live_class_id') && $request->live_class_id) {
                $query->where('live_class_id', $request->live_class_id);
            }

            if ($request->has('subject_id') && $request->subject_id) {
                $query->where('subject_id', $request->subject_id);
            }

            if ($request->has('employee_id') && $request->employee_id) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $assignments = $query->orderBy('created_at', 'desc')->paginate(25);

            return response()->json([
                'status' => 'success',
                'data' => $assignments
            ]);

        } catch (\Exception $e) {
            Log::error('Assignment index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch assignments'
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
                'id' => 'required|exists:assignments,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $assignment = Assignment::where('school_id', $request->school_id)
                ->with([
                    'liveClass', 
                    'subject', 
                    'employee', 
                    'submissions.student', 
                    'submissions.gradedBy'
                ])
                ->find($id);

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Assignment not found or does not belong to this school'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $assignment
            ]);

        } catch (\Exception $e) {
            Log::error('Assignment show error: ' . $e->getMessage());
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
            'live_class_id' => 'required|exists:live_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'employee_id' => 'required|exists:employees,id', // Changed from teacher_id to employee_id
            'title' => 'required|string|max:255',
            'instruction' => 'required|string',
            'attachment' => 'nullable|string',
            'available_from' => 'required|date',
            'due_date' => 'required|date|after:available_from',
            'max_score' => 'required|numeric|min:0|max:999999.99',
            'allow_late_submission' => 'nullable|boolean',
            'status' => 'nullable|in:draft,published,closed'
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

            // Verify live class belongs to school
            $liveClass = LiveClass::where('id', $request->live_class_id)
                ->where('school_id', $request->school_id)
                ->first();

            if (!$liveClass) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Live class not found or does not belong to this school'
                ], 404);
            }

            $assignment = Assignment::create([
                'school_id' => $request->school_id,
                'live_class_id' => $request->live_class_id,
                'subject_id' => $request->subject_id,
                'employee_id' => $request->employee_id, // Changed from teacher_id
                'title' => $request->title,
                'instruction' => $request->instruction,
                'attachment' => $request->attachment,
                'available_from' => Carbon::parse($request->available_from),
                'due_date' => Carbon::parse($request->due_date),
                'max_score' => $request->max_score,
                'allow_late_submission' => $request->allow_late_submission ?? false,
                'status' => $request->status ?? 'draft'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Assignment created successfully',
                'data' => $assignment->load(['liveClass', 'subject', 'employee'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Assignment store error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create assignment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:assignments,id',
            'school_id' => 'required|exists:schools,id',
            'title' => 'sometimes|string|max:255',
            'instruction' => 'sometimes|string',
            'attachment' => 'nullable|string',
            'available_from' => 'sometimes|date',
            'due_date' => 'sometimes|date|after:available_from',
            'max_score' => 'sometimes|numeric|min:0|max:999999.99',
            'allow_late_submission' => 'nullable|boolean',
            'status' => 'nullable|in:draft,published,closed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $assignment = Assignment::where('school_id', $request->school_id)->find($id);

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Assignment not found or does not belong to this school'
                ], 404);
            }

            $data = $request->only([
                'title', 'instruction', 'attachment', 'max_score', 
                'allow_late_submission', 'status'
            ]);

            if ($request->has('available_from')) {
                $data['available_from'] = Carbon::parse($request->available_from);
            }

            if ($request->has('due_date')) {
                $data['due_date'] = Carbon::parse($request->due_date);
            }

            $assignment->update($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Assignment updated successfully',
                'data' => $assignment->fresh(['liveClass', 'subject', 'employee'])
            ]);

        } catch (\Exception $e) {
            Log::error('Assignment update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update assignment'
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
                'id' => 'required|exists:assignments,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $assignment = Assignment::where('school_id', $request->school_id)->find($id);

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Assignment not found or does not belong to this school'
                ], 404);
            }

            $assignment->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Assignment deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Assignment destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete assignment'
            ], 500);
        }
    }

    public function publish(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:assignments,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $assignment = Assignment::where('school_id', $request->school_id)->find($id);

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Assignment not found or does not belong to this school'
                ], 404);
            }

            $assignment->update(['status' => 'published']);

            return response()->json([
                'status' => 'success',
                'message' => 'Assignment published successfully',
                'data' => $assignment
            ]);

        } catch (\Exception $e) {
            Log::error('Assignment publish error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to publish assignment'
            ], 500);
        }
    }

    public function close(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:assignments,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $assignment = Assignment::where('school_id', $request->school_id)->find($id);

            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Assignment not found or does not belong to this school'
                ], 404);
            }

            $assignment->update(['status' => 'closed']);

            return response()->json([
                'status' => 'success',
                'message' => 'Assignment closed successfully',
                'data' => $assignment
            ]);

        } catch (\Exception $e) {
            Log::error('Assignment close error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to close assignment'
            ], 500);
        }
    }

    public function getAssignmentsByLiveClass(Request $request, $liveClassId)
    {
        try {
            $validator = Validator::make([
                'live_class_id' => $liveClassId,
                'school_id' => $request->school_id
            ], [
                'live_class_id' => 'required|exists:live_classes,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $assignments = Assignment::where('school_id', $request->school_id)
                ->where('live_class_id', $liveClassId)
                ->with(['subject', 'employee', 'submissions'])
                ->orderBy('due_date', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $assignments
            ]);

        } catch (\Exception $e) {
            Log::error('Assignment getAssignmentsByLiveClass error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch assignments for live class'
            ], 500);
        }
    }

    public function getAssignmentsByEmployee(Request $request, $employeeId)
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

            $assignments = Assignment::where('school_id', $request->school_id)
                ->where('employee_id', $employeeId)
                ->with(['liveClass', 'subject'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $assignments
            ]);

        } catch (\Exception $e) {
            Log::error('Assignment getAssignmentsByEmployee error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch assignments for employee'
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
                'total' => Assignment::where('school_id', $request->school_id)->count(),
                'draft' => Assignment::where('school_id', $request->school_id)
                    ->where('status', 'draft')
                    ->count(),
                'published' => Assignment::where('school_id', $request->school_id)
                    ->where('status', 'published')
                    ->count(),
                'closed' => Assignment::where('school_id', $request->school_id)
                    ->where('status', 'closed')
                    ->count(),
                'overdue' => Assignment::where('school_id', $request->school_id)
                    ->where('status', 'published')
                    ->where('due_date', '<', $now)
                    ->count(),
                'due_this_week' => Assignment::where('school_id', $request->school_id)
                    ->where('status', 'published')
                    ->whereBetween('due_date', [$now, $now->copy()->endOfWeek()])
                    ->count()
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Assignment getStats error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch assignment statistics'
            ], 500);
        }
    }
}