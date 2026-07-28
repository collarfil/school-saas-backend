<?php

namespace App\Modules\Onlinelearning\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Onlinelearning\Models\Poll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PollController extends Controller
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

            $polls = Poll::where('meeting_id', $request->meeting_id)
                ->whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['createdBy'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $polls
            ]);

        } catch (\Exception $e) {
            Log::error('Poll index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch polls'
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
                'id' => 'required|exists:polls,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $poll = Poll::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['createdBy', 'pollResponses.student'])
                ->find($id);

            if (!$poll) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Poll not found or does not belong to this school'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $poll
            ]);

        } catch (\Exception $e) {
            Log::error('Poll show error: ' . $e->getMessage());
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
            'school_id' => 'required|exists:schools,id',
            'question' => 'required|string',
            'options' => 'required|array|min:2',
            'options.*' => 'required|string',
            'correct_answer' => 'required|string'
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

            // Verify meeting belongs to school
            $meeting = \App\Modules\Onlinelearning\Models\Meeting::where('id', $request->meeting_id)
                ->where('school_id', $request->school_id)
                ->first();

            if (!$meeting) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Meeting not found or does not belong to this school'
                ], 404);
            }

            // Verify correct_answer exists in options
            if (!in_array($request->correct_answer, $request->options)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Correct answer must be one of the provided options'
                ], 422);
            }

            $poll = Poll::create([
                'meeting_id' => $request->meeting_id,
                'question' => $request->question,
                'options' => $request->options,
                'correct_answer' => $request->correct_answer,
                'created_by' => $request->user()->id
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Poll created successfully',
                'data' => $poll->load(['createdBy'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Poll store error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create poll: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:polls,id',
            'school_id' => 'required|exists:schools,id',
            'question' => 'sometimes|string',
            'options' => 'sometimes|array|min:2',
            'options.*' => 'required|string',
            'correct_answer' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $poll = Poll::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$poll) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Poll not found or does not belong to this school'
                ], 404);
            }

            $data = $request->only(['question', 'options', 'correct_answer']);

            // If options are being updated, verify correct_answer is still valid
            if (isset($data['options']) && isset($data['correct_answer'])) {
                if (!in_array($data['correct_answer'], $data['options'])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Correct answer must be one of the provided options'
                    ], 422);
                }
            } elseif (isset($data['options']) && !isset($data['correct_answer'])) {
                // If options updated but correct_answer not, check if current correct_answer exists in new options
                if (!in_array($poll->correct_answer, $data['options'])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Current correct answer is not in the updated options. Please update the correct_answer as well.'
                    ], 422);
                }
            } elseif (isset($data['correct_answer']) && !isset($data['options'])) {
                // If only correct_answer updated, verify it exists in current options
                if (!in_array($data['correct_answer'], $poll->options)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Correct answer must be one of the existing options'
                    ], 422);
                }
            }

            $poll->update($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Poll updated successfully',
                'data' => $poll->fresh(['createdBy'])
            ]);

        } catch (\Exception $e) {
            Log::error('Poll update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update poll'
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
                'id' => 'required|exists:polls,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $poll = Poll::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$poll) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Poll not found or does not belong to this school'
                ], 404);
            }

            // Delete associated responses first (cascade should handle this, but being explicit)
            $poll->pollResponses()->delete();
            $poll->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Poll deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Poll destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete poll'
            ], 500);
        }
    }

    public function getResults(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:polls,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $poll = Poll::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['pollResponses.student'])
                ->find($id);

            if (!$poll) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Poll not found or does not belong to this school'
                ], 404);
            }

            $responses = $poll->pollResponses;
            $totalResponses = $responses->count();

            // Calculate results per option
            $results = [];
            foreach ($poll->options as $option) {
                $count = $responses->where('selected_option', $option)->count();
                $results[] = [
                    'option' => $option,
                    'count' => $count,
                    'percentage' => $totalResponses > 0 ? round(($count / $totalResponses) * 100, 2) : 0
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'poll' => $poll,
                    'total_responses' => $totalResponses,
                    'results' => $results,
                    'correct_answer' => $poll->correct_answer,
                    'correct_count' => $responses->where('selected_option', $poll->correct_answer)->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Poll getResults error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get poll results'
            ], 500);
        }
    }
}