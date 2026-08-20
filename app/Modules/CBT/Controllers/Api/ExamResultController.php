<?php

namespace App\Modules\Cbt\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Cbt\Models\ExamResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ExamResultController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'school_id' => 'required|exists:schools,id',
                'exam_id' => 'nullable|exists:exams,id',
                'student_id' => 'nullable|exists:students,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = ExamResult::where('school_id', $request->school_id)
                ->with(['exam', 'student', 'examSession']);

            if ($request->filled('exam_id')) {
                $query->where('exam_id', $request->exam_id);
            }
            if ($request->filled('student_id')) {
                $query->where('student_id', $request->student_id);
            }

            $results = $query->orderBy('score_obtained', 'desc')->get();

            return response()->json([
                'status' => 'success',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('ExamResultController index runtime failure: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to resolve historical result lists data grids.',
                'error' => $e->getMessage()
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
                'id' => 'required|exists:exam_results,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = ExamResult::where('school_id', $request->school_id)
                ->with(['exam.questions.options', 'student', 'examSession.responses'])
                ->find($id);

            if (!$result) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Result record could not be parsed or cross-school check bounds locked.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('ExamResultController show trace error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Fatal exception caught trying to construct structural performance models.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Manual scoring endpoint for theory items/override configurations
    public function gradeTheory(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:exam_results,id',
            'school_id' => 'required|exists:schools,id',
            'score_obtained' => 'required|numeric|min:0',
            'teacher_remarks' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = ExamResult::where('school_id', $request->school_id)
                ->with('exam')
                ->find($id);

            if (!$result) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Target entity missing inside context scope bounds.'
                ], 404);
            }

            $exam = $result->exam;
            if ($request->score_obtained > $exam->max_score) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Score cannot scale higher than max absolute target bounds value: ' . $exam->max_score
                ], 422);
            }

            $percentage = ($request->score_obtained / $exam->max_score) * 100;
            $isPassed = $request->score_obtained >= $exam->pass_mark;

            $result->update([
                'score_obtained' => $request->score_obtained,
                'percentage' => $percentage,
                'is_passed' => $isPassed,
                'graded_by' => auth()->id() ?? null,
                'teacher_remarks' => $request->teacher_remarks
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Manual metrics applied onto the examination structure safely.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('ExamResultController gradeTheory failure: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to apply updated manual scores values.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}