<?php

namespace App\Modules\Cbt\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Cbt\Models\ExamSession;
use App\Modules\Cbt\Models\Exam;
use App\Modules\Cbt\Models\Question;
use App\Modules\Cbt\Models\Option;
use App\Modules\Cbt\Models\ExamResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ExamSessionController extends Controller
{
    // Start/Initialize an Exam Session for a Student
    public function startSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'exam_id' => 'required|exists:exams,id',
            'student_id' => 'required|exists:students,id',
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
            if (!$exam || $exam->status !== 'published') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Exam is unavailable or not yet published.'
                ], 400);
            }

            // Check if active session or completed result exists
            $existingSession = ExamSession::where('exam_id', $request->exam_id)
                ->where('student_id', $request->student_id)
                ->first();

            if ($existingSession) {
                if ($existingSession->status === 'completed') {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'You have already submitted this exam.'
                    ], 400);
                }
                
                // Return existing live session context if already processing tracking data safely
                return response()->json([
                    'status' => 'success',
                    'message' => 'Resuming existing running CBT session.',
                    'data' => $existingSession
                ]);
            }

            $startedAt = now();
            $expiresAt = $startedAt->copy()->addMinutes($exam->duration_minutes);

            $session = ExamSession::create([
                'school_id' => $request->school_id,
                'exam_id' => $request->exam_id,
                'student_id' => $request->student_id,
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Exam session started successfully.',
                'data' => $session
            ], 201);
        } catch (\Exception $e) {
            Log::error('ExamSession startSession runtime exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to initialize active tracking sequence.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Save/Sync a response mid-exam via AJAX/Fetch calls
    public function saveResponse(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_session_id' => 'required|exists:exam_sessions,id',
            'question_id' => 'required|exists:questions,id',
            'option_id' => 'nullable|exists:options,id',
            'text_answer' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $session = ExamSession::find($request->exam_session_id);
            if ($session->status !== 'active' || $session->isExpired()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This examination context session has locked or timed out.'
                ], 403);
            }

            $question = Question::find($request->question_id);
            $isCorrect = false;

            // Compute structural context validity parameters immediately for objective scopes
            if ($question->type === 'mcq' || $question->type === 'boolean') {
                if ($request->filled('option_id')) {
                    $option = Option::where('question_id', $request->question_id)->find($request->option_id);
                    $isCorrect = $option ? $option->is_correct : false;
                }
            }

            $response = $session->responses()->updateOrCreate(
                ['question_id' => $request->question_id],
                [
                    'option_id' => $request->option_id,
                    'text_answer' => $request->text_answer,
                    'is_correct' => $isCorrect
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Progress state synced successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('ExamSession saveResponse runtime error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Friction encountered synchronization execution metrics.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Finalize and submit the complete session
    public function submitSession(Request $request, $id)
    {
        try {
            $session = ExamSession::with(['exam.questions.options', 'responses'])->find($id);

            if (!$session || $session->status === 'completed') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session terminated or data bounds unreachable.'
                ], 400);
            }

            DB::beginTransaction();

            $session->update([
                'submitted_at' => now(),
                'status' => 'completed'
            ]);

            $exam = $session->exam;
            $totalScore = 0;
            $requiresManualGrading = false;

            foreach ($exam->questions as $question) {
                $studentResponse = $session->responses->where('question_id', $question->id)->first();
                
                if ($question->type === 'theory') {
                    $requiresManualGrading = true;
                    continue;
                }

                if ($studentResponse && $studentResponse->is_correct) {
                    $totalScore += $question->marks;
                }
            }

            $percentage = ($exam->max_score > 0) ? ($totalScore / $exam->max_score) * 100 : 0;
            $isPassed = $totalScore >= $exam->pass_mark;

            $result = ExamResult::updateOrCreate(
                ['exam_session_id' => $session->id],
                [
                    'school_id' => $session->school_id,
                    'exam_id' => $session->exam_id,
                    'student_id' => $session->student_id,
                    'score_obtained' => $totalScore,
                    'percentage' => $percentage,
                    'is_passed' => $isPassed,
                    'teacher_remarks' => $requiresManualGrading ? 'Pending manual theory item cross-evaluation metrics validation.' : ($isPassed ? 'Passed' : 'Failed')
                ]
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Examination data session closed and structured cleanly.',
                'data' => [
                    'session_status' => 'completed',
                    'requires_manual_grading' => $requiresManualGrading,
                    'score_obtained' => $exam->show_result_immediately ? $totalScore : 'Hidden',
                    'percentage' => $exam->show_result_immediately ? $percentage : 'Hidden'
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ExamSession submitSession transaction error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Processing absolute validation sequence parameters broke out.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}