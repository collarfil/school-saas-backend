<?php

namespace App\Modules\CBT\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\CBT\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ExamGradeController extends Controller
{
    // Fetch all academic grades attached to a particular target exam allocation rule matrix
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'exam_id' => 'required|exists:exams,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $exam = Exam::where('school_id', $request->school_id)->find($request->exam_id);
            if (!$exam) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Exam scope mismatch error or out of bounds access attempt.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $exam->grades()->get()
            ]);
        } catch (\Exception $e) {
            Log::error('ExamGradeController index execution breakdown: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process assigned distribution class allocations.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Explicit manual pivot synchronization mapping overrides
    public function syncGrades(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_id' => 'required|exists:exams,id',
            'school_id' => 'required|exists:schools,id',
            'grade_ids' => 'required|array',
            'grade_ids.*' => 'exists:grades,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $exam = Exam::where('school_id', $request->school_id)->find($request->exam_id);
            if (!$exam) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Exam ruleset configuration map out of structural bounds.'
                ], 404);
            }

            // Synchronize link attachments directly
            $exam->grades()->sync($request->grade_ids);

            return response()->json([
                'status' => 'success',
                'message' => 'Target classrooms allocation synced successfully across CBT bounds.',
                'data' => $exam->fresh(['grades'])->grades
            ]);
        } catch (\Exception $e) {
            Log::error('ExamGradeController syncGrades transactional error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed processing classroom distribution target mappings arrays.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}