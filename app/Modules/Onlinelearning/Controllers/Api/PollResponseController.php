<?php

namespace App\Modules\Onlinelearning\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Onlinelearning\Models\Poll;
use App\Modules\Onlinelearning\Models\PollResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PollResponseController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'poll_id' => 'required|exists:polls,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $responses = PollResponse::where('poll_id', $request->poll_id)
                ->whereHas('poll', function ($query) use ($request) {
                    $query->whereHas('meeting', function ($q) use ($request) {
                        $q->where('school_id', $request->school_id);
                    });
                })
                ->with(['poll', 'student'])
                ->orderBy('answered_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $responses
            ]);

        } catch (\Exception $e) {
            Log::error('PollResponse index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch poll responses'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'poll_id' => 'required|exists:polls,id',
            'student_id' => 'required|exists:users,id',
            'selected_option' => 'required|string'
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

            $poll = Poll::find($request->poll_id);
            if (!$poll) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Poll not found'
                ], 404);
            }

            // Verify selected option exists in poll options
            if (!in_array($request->selected_option, $poll->options)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid selected option'
                ], 422);
            }

            // Check if student already responded
            $existing = PollResponse::where('poll_id', $request->poll_id)
                ->where('student_id', $request->student_id)
                ->first();

            if ($existing) {
                // Update existing response
                $existing->update([
                    'selected_option' => $request->selected_option,
                    'answered_at' => Carbon::now()
                ]);

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Poll response updated successfully',
                    'data' => $existing->load(['poll', 'student'])
                ]);
            }

            $response = PollResponse::create([
                'poll_id' => $request->poll_id,
                'student_id' => $request->student_id,
                'selected_option' => $request->selected_option,
                'answered_at' => Carbon::now()
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Poll response submitted successfully',
                'data' => $response->load(['poll', 'student'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PollResponse store error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit poll response: ' . $e->getMessage()
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
                'id' => 'required|exists:poll_responses,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $response = PollResponse::whereHas('poll', function ($query) use ($request) {
                    $query->whereHas('meeting', function ($q) use ($request) {
                        $q->where('school_id', $request->school_id);
                    });
                })
                ->find($id);

            if (!$response) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Poll response not found or does not belong to this school'
                ], 404);
            }

            $response->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Poll response deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('PollResponse destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete poll response'
            ], 500);
        }
    }

    public function getStudentResponse(Request $request, $pollId, $studentId)
    {
        try {
            $validator = Validator::make([
                'poll_id' => $pollId,
                'student_id' => $studentId,
                'school_id' => $request->school_id
            ], [
                'poll_id' => 'required|exists:polls,id',
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

            $response = PollResponse::where('poll_id', $pollId)
                ->where('student_id', $studentId)
                ->whereHas('poll', function ($query) use ($request) {
                    $query->whereHas('meeting', function ($q) use ($request) {
                        $q->where('school_id', $request->school_id);
                    });
                })
                ->with(['poll', 'student'])
                ->first();

            return response()->json([
                'status' => 'success',
                'data' => $response
            ]);

        } catch (\Exception $e) {
            Log::error('PollResponse getStudentResponse error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get student response'
            ], 500);
        }
    }

    public function getPollStatistics(Request $request, $pollId)
    {
        try {
            $validator = Validator::make([
                'poll_id' => $pollId,
                'school_id' => $request->school_id
            ], [
                'poll_id' => 'required|exists:polls,id',
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
                ->find($pollId);

            if (!$poll) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Poll not found or does not belong to this school'
                ], 404);
            }

            $responses = PollResponse::where('poll_id', $pollId)->get();
            $totalResponses = $responses->count();

            $statistics = [];
            foreach ($poll->options as $option) {
                $count = $responses->where('selected_option', $option)->count();
                $statistics[] = [
                    'option' => $option,
                    'count' => $count,
                    'percentage' => $totalResponses > 0 ? round(($count / $totalResponses) * 100, 2) : 0
                ];
            }

            $isCorrect = $responses->where('selected_option', $poll->correct_answer)->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'poll_id' => $pollId,
                    'total_responses' => $totalResponses,
                    'statistics' => $statistics,
                    'correct_answer' => $poll->correct_answer,
                    'correct_responses' => $isCorrect,
                    'correct_percentage' => $totalResponses > 0 ? round(($isCorrect / $totalResponses) * 100, 2) : 0
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('PollResponse getPollStatistics error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get poll statistics'
            ], 500);
        }
    }
}