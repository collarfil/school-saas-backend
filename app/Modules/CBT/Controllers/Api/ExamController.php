<?php

namespace App\Modules\Cbt\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Cbt\Models\Exam;
use App\Modules\CBT\Models\ExamType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    public function index(Request $request)
{
    $validator = Validator::make($request->all(), [
        'school_id' => 'required|exists:schools,id',
        'school_session_id' => 'required|exists:school_sessions,id', // Added requirement
        'status' => 'nullable|string|in:draft,published,closed'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $query = Exam::where('school_id', $request->school_id)
            ->where('school_session_id', $request->school_session_id); // Filtered

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->with(['examType', 'subject'])->get()
        ]);
    } catch (\Exception $e) {
        Log::error('ExamController index filtering failure: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to resolve session isolated active test records.',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'school_id' => 'required|exists:schools,id',
        'school_session_id' => 'required|exists:school_sessions,id',
        'exam_type_id' => 'required|exists:exam_types,id',
        'subject_id' => 'required|exists:subjects,id',
        'employee_id' => 'required|exists:employees,id',
        'grade_ids' => 'required|array|min:1',
        'grade_ids.*' => 'exists:grades,id',
        'title' => 'required|string|max:255',
        'instruction' => 'nullable|string',
        'available_from' => 'required|date',
        'due_date' => 'required|date|after:available_from',
        'duration_minutes' => 'required|integer|min:1',
        'max_score' => 'required|numeric|min:0',
        'pass_mark' => 'required|numeric|min:0',
        'randomize_questions' => 'nullable|boolean',
        'randomize_options' => 'nullable|boolean',
        'show_result_immediately' => 'nullable|boolean',
        'allow_late_submission' => 'nullable|boolean',
        'status' => 'nullable|string|in:draft,published,closed',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        // Confirm the selected exam type belongs to the selected academic session
        $validType = ExamType::where('id', $request->exam_type_id)
            ->where('school_session_id', $request->school_session_id)
            ->exists();

        if (!$validType) {
            return response()->json([
                'status' => 'error',
                'message' => 'The provided Exam Type does not belong to the selected academic session configuration bounds.'
            ], 422);
        }

        // Use validated data to avoid passing unmapped array keys into create()
        $validatedData = $validator->validated();

        // Create the exam instance (excluding grade_ids)
        $examData = collect($validatedData)->except(['grade_ids'])->toArray();
        $exam = Exam::create($examData);

        // Sync target grades to the pivot table (exam_grade)
        $exam->grades()->sync($request->grade_ids);

        // Load relationships for the API response
        $exam->load(['examType', 'subject', 'employee', 'grades']);

        return response()->json([
            'status' => 'success',
            'message' => 'CBT structural deployment matrix mapped securely.',
            'data' => $exam
        ], 201);
    } catch (\Exception $e) {
        Log::error('ExamController store configuration failure: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to initiate active runtime CBT testing unit profile.',
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
                'id' => 'required|exists:exams,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $exam = Exam::where('school_id', $request->school_id)
                ->with(['examType', 'subject', 'employee', 'grades', 'questions.options'])
                ->find($id);

            if (!$exam) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Exam item not found or cross-school bounds breach.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $exam
            ]);
        } catch (\Exception $e) {
            Log::error('ExamController show error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
{
    $exam = Exam::findOrFail($id);

    $validator = Validator::make($request->all(), [
        'exam_type_id' => 'sometimes|required|exists:exam_types,id',
        'subject_id' => 'sometimes|required|exists:subjects,id',
        'employee_id' => 'sometimes|required|exists:employees,id',
        'grade_ids' => 'sometimes|required|array|min:1',
        'grade_ids.*' => 'exists:grades,id',
        'title' => 'sometimes|required|string|max:255',
        'available_from' => 'sometimes|required|date',
        'due_date' => 'sometimes|required|date|after:available_from',
        'duration_minutes' => 'sometimes|required|integer|min:1',
        'max_score' => 'sometimes|required|numeric',
        'pass_mark' => 'sometimes|required|numeric',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $validatedData = $validator->validated();

        $examData = collect($validatedData)->except(['grade_ids'])->toArray();
        $exam->update($examData);

        if ($request->has('grade_ids')) {
            $exam->grades()->sync($request->grade_ids);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Exam updated successfully.',
            'data' => $exam->load(['examType', 'subject', 'employee', 'grades'])
        ]);
    } catch (\Exception $e) {
        Log::error('ExamController update failure: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to update exam record.'
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
                'id' => 'required|exists:exams,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $exam = Exam::where('school_id', $request->school_id)->find($id);

            if (!$exam) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Exam structure context not found.'
                ], 404);
            }

            if ($exam->sessions()->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot remove exam structural rules because ongoing/past student data sessions are actively anchored to it.'
                ], 422);
            }

            DB::beginTransaction();
            $exam->grades()->detach();
            $exam->delete();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Exam structure securely scrubbed.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ExamController destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete target exam blueprint',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}