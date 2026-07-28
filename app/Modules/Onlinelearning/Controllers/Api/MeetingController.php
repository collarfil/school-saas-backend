<?php

namespace App\Modules\Onlinelearning\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Onlinelearning\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MeetingController extends Controller
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

            $messages = Meeting::where('id', $request->meeting_id)
                ->whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['user', 'replies'])
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $messages
            ]);

        } catch (\Exception $e) {
            Log::error('Meeting index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch messages'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meeting_id' => 'required|exists:meetings,id',
            'school_id' => 'required|exists:schools,id',
            'message' => 'required|string',
            'reply_to' => 'nullable|exists:messages,id',
            'is_teacher_message' => 'nullable|boolean'
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

            // If replying to a message, verify it exists and belongs to the same meeting
            if ($request->reply_to) {
                $parentMessage = Meeting::where('id', $request->reply_to)
                    ->where('meeting_id', $request->meeting_id)
                    ->first();

                if (!$parentMessage) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Parent message not found or does not belong to this meeting'
                    ], 404);
                }
            }

            $message = Meeting::create([
                'meeting_id' => $request->meeting_id,
                'user_id' => $request->user()->id,
                'message' => $request->message,
                'reply_to' => $request->reply_to,
                'is_teacher_message' => $request->is_teacher_message ?? false,
                'created_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Message sent successfully',
                'data' => $message->load(['user', 'replies'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Meeting store error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send message: ' . $e->getMessage()
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
                'id' => 'required|exists:messages,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $message = Meeting::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$message) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Message not found or does not belong to this school'
                ], 404);
            }

            // Check if user is authorized to delete
            if ($message->user_id !== $request->user()->id && !$request->user()->isSuperAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not authorized to delete this message'
                ], 403);
            }

            // Delete all replies first
            $message->replies()->delete();
            $message->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Message deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Meeting destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete message'
            ], 500);
        }
    }

    public function getThread(Request $request, $messageId)
    {
        try {
            $validator = Validator::make([
                'message_id' => $messageId,
                'school_id' => $request->school_id
            ], [
                'message_id' => 'required|exists:messages,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $message = Meeting::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['user', 'replies.user', 'replies.replies'])
                ->find($messageId);

            if (!$message) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Message not found or does not belong to this school'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('ClassAttendance getThread error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch message thread'
            ], 500);
        }
    }

    public function markAsTeacher(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:messages,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $message = Meeting::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$message) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Message not found or does not belong to this school'
                ], 404);
            }

            $message->update(['is_teacher_message' => true]);

            return response()->json([
                'status' => 'success',
                'message' => 'Message marked as teacher message',
                'data' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('ClassAttendance markAsTeacher error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark message as teacher message'
            ], 500);
        }
    }
}