<?php

namespace App\Modules\CBT\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\CBT\Models\StudentResponse;
use App\Modules\CBT\Models\ExamSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class StudentResponseController extends Controller
{
    // System utility controller allowing administrative oversight/auditing of exact response streams
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'exam_session_id' => 'required|exists:exam_sessions,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $responses = StudentResponse::where('exam_session_id', $request->exam_session_id)
                ->with(['question.options', 'option'])
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $responses
            ]);
        } catch (\Exception $e) {
            Log::error('StudentResponseController index processing failure: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to resolve raw trace audit lines tracking data sheets.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}