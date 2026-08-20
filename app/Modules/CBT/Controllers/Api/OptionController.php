<?php

namespace App\Modules\CBT\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\CBT\Models\Option;
use App\Modules\CBT\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class OptionController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|exists:questions,id',
            'option_text' => 'required|string',
            'option_image' => 'nullable|string',
            'is_correct' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $question = Question::find($request->question_id);
            
            if ($question->type === 'theory') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot add multiple-choice options to a theoretical question type.'
                ], 422);
            }

            // If it's boolean type and already has 2 items, block it
            if ($question->type === 'boolean' && $question->options()->count() >= 2) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Boolean questions cannot contain more than two alternative choices.'
                ], 422);
            }

            $option = Option::create($request->only([
                'question_id', 'option_text', 'option_image', 'is_correct'
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Question choice option generated successfully.',
                'data' => $option
            ], 201);
        } catch (\Exception $e) {
            Log::error('OptionController store error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create question option structural entry.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
                'id' => 'required|exists:options,id',
                'option_text' => 'sometimes|string',
                'option_image' => 'nullable|string',
                'is_correct' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $option = Option::find($id);
            if (!$option) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Target option element not found.'
                ], 404);
            }

            $option->update($request->only(['option_text', 'option_image', 'is_correct']));

            return response()->json([
                'status' => 'success',
                'message' => 'Option metrics modified cleanly.',
                'data' => $option
            ]);
        } catch (\Exception $e) {
            Log::error('OptionController update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update target choice configuration parameters.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $option = Option::find($id);
            if (!$option) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Target element missing.'
                ], 404);
            }

            $option->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Choice entry item dropped securely.'
            ]);
        } catch (\Exception $e) {
            Log::error('OptionController destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to drop option item matrix values.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}