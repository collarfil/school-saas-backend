<?php

namespace App\Modules\Cbt\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Cbt\Models\Question;
use App\Modules\Cbt\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'exam_id' => 'required|exists:exams,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $questions = Question::where('exam_id', $request->exam_id)
                ->with('options')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $questions
            ]);
        } catch (\Exception $e) {
            Log::error('QuestionController index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch questions.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_id' => 'required|exists:exams,id',
            'question_text' => 'required|string',
            'question_image' => 'nullable|string',
            'type' => 'required|string|in:mcq,boolean,theory',
            'marks' => 'required|numeric|min:0',
            'explanation' => 'nullable|string',
            'options' => 'required_if:type,mcq,boolean|array|min:2',
            'options.*.option_text' => 'required|string',
            'options.*.option_image' => 'nullable|string',
            'options.*.is_correct' => 'required|boolean'
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

            $question = Question::create($request->only([
                'exam_id', 'question_text', 'question_image', 'type', 'marks', 'explanation'
            ]));

            if (in_array($request->type, ['mcq', 'boolean'])) {
                $hasCorrectOption = false;
                foreach ($request->options as $optionData) {
                    if ($optionData['is_correct']) {
                        $hasCorrectOption = true;
                    }
                    $question->options()->create($optionData);
                }

                if (!$hasCorrectOption) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Objective items must have at least one defined correct key answer option setup.'
                    ], 422);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Question matrix saved successfully',
                'data' => $question->load('options')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Question setup runtime breakdown: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to structuralize targeted question parameters.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $question = Question::with('options')->find($id);

            if (!$question) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Question component could not be resolved.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $question
            ]);
        } catch (\Exception $e) {
            Log::error('QuestionController show execution exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Structural execution error thrown.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
                'id' => 'required|exists:questions,id',
                'question_text' => 'sometimes|string',
                'question_image' => 'nullable|string',
                'marks' => 'sometimes|numeric|min:0',
                'explanation' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $question = Question::find($id);
            if (!$question) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Question context entity missing.'
                ], 404);
            }

            $question->update($request->only(['question_text', 'question_image', 'marks', 'explanation']));

            return response()->json([
                'status' => 'success',
                'message' => 'Core text values modified successfully.',
                'data' => $question->load('options')
            ]);
        } catch (\Exception $e) {
            Log::error('Question update failure: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Processing updates failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $question = Question::find($id);
            if (!$question) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Target entity missing.'
                ], 404);
            }

            DB::beginTransaction();
            $question->options()->delete();
            $question->delete();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Question element and child key configurations cleanly discarded.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Question wiping action failure: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Execution context scrub termination interrupted.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}