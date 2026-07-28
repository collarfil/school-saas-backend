<?php

namespace App\Modules\Onlinelearning\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Onlinelearning\Models\Assignment;
use App\Modules\Onlinelearning\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AssignmentSubmissionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'school_id' => 'required|exists:schools,id',
                'assignment_id' => 'nullable|exists:assignments,id',
                'student_id' => 'nullable|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = AssignmentSubmission::whereHas('assignment', function ($q) use ($request) {
                    $q->where('school_id', $request->school_id);
                })
                ->with(['assignment', 'student', 'gradedBy']);

            if ($request->has('assignment_id') && $request->assignment_id) {
                $query->where('assignment_id', $request->assignment_id);
            }

            if ($request->has('student_id') && $request->student_id) {
                $query->where('student_id', $request->student_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $submissions = $query->orderBy('created_at', 'desc')->paginate(25);

            return response()->json([
                'status' => 'success',
                'data' => $submissions
            ]);

        } catch (\Exception $e) {
            Log::error('AssignmentSubmission index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch submissions'
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
                'id' => 'required|exists:assignment_submissions,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $submission = AssignmentSubmission::whereHas('assignment', function ($q) use ($request) {
                    $q->where('school_id', $request->school_id);
                })
                ->with(['assignment', 'student', 'gradedBy'])
                ->find($id);

            if (!$submission) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Submission not found or does not belong to this school'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $submission
            ]);

        } catch (\Exception $e) {
            Log::error('AssignmentSubmission show error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assignment_id' => 'required|exists:assignments,id',
            'student_id' => 'required|exists:users,id',
            'submission_text' => 'nullable|string',
            'attachment' => 'nullable|string'
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

            // Check if assignment exists and is published
            $assignment = Assignment::find($request->assignment_id);
            if (!$assignment || $assignment->status !== 'published') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Assignment is not available for submission'
                ], 400);
            }

            // Check if assignment is past due date
            $now = Carbon::now();
            if ($now->gt($assignment->due_date) && !$assignment->allow_late_submission) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Submission deadline has passed and late submissions are not allowed'
                ], 400);
            }

            // Check for existing submission
            $existing = AssignmentSubmission::where('assignment_id', $request->assignment_id)
                ->where('student_id', $request->student_id)
                ->first();

            if ($existing) {
                // Allow resubmission by updating
                $existing->update([
                    'submission_text' => $request->submission_text,
                    'attachment' => $request->attachment,
                    'submitted_at' => $now,
                    'status' => 'submitted',
                    'score' => null,
                    'remark' => null,
                    'graded_by' => null,
                    'graded_at' => null
                ]);

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Submission updated successfully',
                    'data' => $existing->fresh(['assignment', 'student'])
                ]);
            }

            $submission = AssignmentSubmission::create([
                'assignment_id' => $request->assignment_id,
                'student_id' => $request->student_id,
                'submission_text' => $request->submission_text,
                'attachment' => $request->attachment,
                'submitted_at' => $now,
                'status' => 'submitted'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Assignment submitted successfully',
                'data' => $submission->load(['assignment', 'student'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AssignmentSubmission store error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit assignment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function grade(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:assignment_submissions,id',
            'school_id' => 'required|exists:schools,id',
            'score' => 'required|numeric|min:0|max:999999.99',
            'remark' => 'nullable|string'
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

            $submission = AssignmentSubmission::whereHas('assignment', function ($q) use ($request) {
                    $q->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$submission) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Submission not found or does not belong to this school'
                ], 404);
            }

            // Validate score against assignment max score
            $assignment = $submission->assignment;
            if ($request->score > $assignment->max_score) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Score cannot exceed the maximum score of {$assignment->max_score}"
                ], 422);
            }

            $submission->update([
                'score' => $request->score,
                'remark' => $request->remark,
                'graded_by' => $request->user()->id,
                'graded_at' => Carbon::now(),
                'status' => 'graded'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Assignment graded successfully',
                'data' => $submission->fresh(['assignment', 'student', 'gradedBy'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AssignmentSubmission grade error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to grade submission'
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
                'id' => 'required|exists:assignment_submissions,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $submission = AssignmentSubmission::whereHas('assignment', function ($q) use ($request) {
                    $q->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$submission) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Submission not found or does not belong to this school'
                ], 404);
            }

            $submission->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Submission deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('AssignmentSubmission destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete submission'
            ], 500);
        }
    }

    public function getStudentSubmissions(Request $request, $studentId)
    {
        try {
            $validator = Validator::make([
                'student_id' => $studentId,
                'school_id' => $request->school_id
            ], [
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

            $submissions = AssignmentSubmission::where('student_id', $studentId)
                ->whereHas('assignment', function ($q) use ($request) {
                    $q->where('school_id', $request->school_id);
                })
                ->with(['assignment', 'assignment.subject', 'gradedBy'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $submissions
            ]);

        } catch (\Exception $e) {
            Log::error('AssignmentSubmission getStudentSubmissions error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch student submissions'
            ], 500);
        }
    }
}