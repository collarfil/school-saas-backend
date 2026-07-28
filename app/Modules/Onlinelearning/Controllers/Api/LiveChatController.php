<?php

namespace App\Modules\Onlinelearning\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Onlinelearning\Models\LiveChat;
use App\Modules\Onlinelearning\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LiveChatController extends Controller
{
    /**
     * Get all messages for a meeting
     */
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

            $messages = LiveChat::where('meeting_id', $request->meeting_id)
                ->whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['user', 'replies.user'])
                ->whereNull('reply_to')
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $messages
            ]);

        } catch (\Exception $e) {
            Log::error('LiveChat index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch chat messages'
            ], 500);
        }
    }

    /**
     * Get a specific message with its replies
     */
    public function show(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:live_chat,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $message = LiveChat::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['user', 'replies.user', 'parentMessage.user'])
                ->find($id);

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
            Log::error('LiveChat show error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred'
            ], 500);
        }
    }

    /**
     * Send a new chat message
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meeting_id' => 'required|exists:meetings,id',
            'school_id' => 'required|exists:schools,id',
            'message' => 'required|string',
            'reply_to' => 'nullable|exists:live_chat,id',
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
            $meeting = Meeting::where('id', $request->meeting_id)
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
                $parentMessage = LiveChat::where('id', $request->reply_to)
                    ->where('meeting_id', $request->meeting_id)
                    ->first();

                if (!$parentMessage) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Parent message not found or does not belong to this meeting'
                    ], 404);
                }
            }

            $chatMessage = LiveChat::create([
                'meeting_id' => $request->meeting_id,
                'user_id' => $request->user()->id,
                'message' => $request->message,
                'reply_to' => $request->reply_to,
                'is_teacher_message' => $request->is_teacher_message ?? false,
                'created_at' => Carbon::now()
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Message sent successfully',
                'data' => $chatMessage->load(['user', 'replies'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('LiveChat store error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send message: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a chat message (only the message content)
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:live_chat,id',
            'school_id' => 'required|exists:schools,id',
            'message' => 'required|string',
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
            $chatMessage = LiveChat::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$chatMessage) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Message not found or does not belong to this school'
                ], 404);
            }

            // Check if user is authorized to update
            if ($chatMessage->user_id !== $request->user()->id && !$request->user()->isSuperAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not authorized to update this message'
                ], 403);
            }

            $chatMessage->update([
                'message' => $request->message,
                'is_teacher_message' => $request->is_teacher_message ?? $chatMessage->is_teacher_message
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Message updated successfully',
                'data' => $chatMessage->fresh(['user', 'replies'])
            ]);

        } catch (\Exception $e) {
            Log::error('LiveChat update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update message'
            ], 500);
        }
    }

    /**
     * Delete a chat message and all its replies
     */
    public function destroy(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:live_chat,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $chatMessage = LiveChat::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$chatMessage) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Message not found or does not belong to this school'
                ], 404);
            }

            // Check if user is authorized to delete
            if ($chatMessage->user_id !== $request->user()->id && !$request->user()->isSuperAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not authorized to delete this message'
                ], 403);
            }

            // Delete all replies first (cascade will handle this if set, but being explicit)
            LiveChat::where('reply_to', $id)->delete();
            $chatMessage->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Message deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('LiveChat destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete message'
            ], 500);
        }
    }

    /**
     * Get messages by meeting with optional filters
     */
    public function getMessagesByMeeting(Request $request, $meetingId)
    {
        try {
            $validator = Validator::make([
                'meeting_id' => $meetingId,
                'school_id' => $request->school_id
            ], [
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

            $query = LiveChat::where('meeting_id', $meetingId)
                ->whereHas('meeting', function ($q) use ($request) {
                    $q->where('school_id', $request->school_id);
                })
                ->with(['user']);

            // Filter by teacher messages
            if ($request->has('teacher_only') && $request->teacher_only) {
                $query->where('is_teacher_message', true);
            }

            // Filter by user
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by date range
            if ($request->has('from_date') && $request->from_date) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->from_date));
            }

            if ($request->has('to_date') && $request->to_date) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->to_date));
            }

            // Only get parent messages or all messages
            if ($request->has('include_replies') && !$request->include_replies) {
                $query->whereNull('reply_to');
            }

            $messages = $query->orderBy('created_at', 'asc')->get();

            // If include_replies is true, load replies for each message
            if ($request->has('include_replies') && $request->include_replies) {
                $messages->load(['replies.user']);
            }

            return response()->json([
                'status' => 'success',
                'data' => $messages
            ]);

        } catch (\Exception $e) {
            Log::error('LiveChat getMessagesByMeeting error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch meeting messages'
            ], 500);
        }
    }

    /**
     * Get all messages from a specific user in a meeting
     */
    public function getUserMessages(Request $request, $meetingId, $userId)
    {
        try {
            $validator = Validator::make([
                'meeting_id' => $meetingId,
                'user_id' => $userId,
                'school_id' => $request->school_id
            ], [
                'meeting_id' => 'required|exists:meetings,id',
                'user_id' => 'required|exists:users,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $messages = LiveChat::where('meeting_id', $meetingId)
                ->where('user_id', $userId)
                ->whereHas('meeting', function ($q) use ($request) {
                    $q->where('school_id', $request->school_id);
                })
                ->with(['user', 'replies.user'])
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $messages
            ]);

        } catch (\Exception $e) {
            Log::error('LiveChat getUserMessages error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch user messages'
            ], 500);
        }
    }

    /**
     * Get message thread (message with all its replies)
     */
    public function getThread(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:live_chat,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $message = LiveChat::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['user', 'replies.user', 'replies.replies.user'])
                ->find($id);

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
            Log::error('LiveChat getThread error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch message thread'
            ], 500);
        }
    }

    /**
     * Get latest messages (for real-time polling)
     */
    public function getLatest(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'meeting_id' => 'required|exists:meetings,id',
                'school_id' => 'required|exists:schools,id',
                'since' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = LiveChat::where('meeting_id', $request->meeting_id)
                ->whereHas('meeting', function ($q) use ($request) {
                    $q->where('school_id', $request->school_id);
                })
                ->with(['user', 'replies.user']);

            if ($request->has('since') && $request->since) {
                $query->where('created_at', '>', Carbon::parse($request->since));
            }

            $messages = $query->orderBy('created_at', 'asc')->get();

            return response()->json([
                'status' => 'success',
                'data' => $messages
            ]);

        } catch (\Exception $e) {
            Log::error('LiveChat getLatest error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch latest messages'
            ], 500);
        }
    }

    /**
     * Mark a message as a teacher message
     */
    public function markAsTeacher(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:live_chat,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $chatMessage = LiveChat::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$chatMessage) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Message not found or does not belong to this school'
                ], 404);
            }

            $chatMessage->update(['is_teacher_message' => true]);

            return response()->json([
                'status' => 'success',
                'message' => 'Message marked as teacher message',
                'data' => $chatMessage
            ]);

        } catch (\Exception $e) {
            Log::error('LiveChat markAsTeacher error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark message as teacher message'
            ], 500);
        }
    }

    /**
     * Get chat statistics for a meeting
     */
    public function getChatStats(Request $request, $meetingId)
    {
        try {
            $validator = Validator::make([
                'meeting_id' => $meetingId,
                'school_id' => $request->school_id
            ], [
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

            $messages = LiveChat::where('meeting_id', $meetingId)
                ->whereHas('meeting', function ($q) use ($request) {
                    $q->where('school_id', $request->school_id);
                })
                ->get();

            $totalMessages = $messages->count();
            $teacherMessages = $messages->where('is_teacher_message', true)->count();
            $studentMessages = $totalMessages - $teacherMessages;
            $replies = $messages->whereNotNull('reply_to')->count();
            $parentMessages = $totalMessages - $replies;

            // Get unique users
            $uniqueUsers = $messages->unique('user_id')->count();

            // Get most active users
            $mostActive = $messages->groupBy('user_id')
                ->map(function ($group) {
                    return [
                        'user_id' => $group->first()->user_id,
                        'user_name' => $group->first()->user->name ?? 'Unknown',
                        'message_count' => $group->count()
                    ];
                })
                ->sortByDesc('message_count')
                ->take(5)
                ->values();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'meeting_id' => $meetingId,
                    'total_messages' => $totalMessages,
                    'teacher_messages' => $teacherMessages,
                    'student_messages' => $studentMessages,
                    'parent_messages' => $parentMessages,
                    'replies' => $replies,
                    'unique_participants' => $uniqueUsers,
                    'most_active_users' => $mostActive
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('LiveChat getChatStats error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch chat statistics'
            ], 500);
        }
    }

    /**
     * Clear all messages in a meeting
     */
    public function clearMeetingChat(Request $request, $meetingId)
    {
        try {
            $validator = Validator::make([
                'meeting_id' => $meetingId,
                'school_id' => $request->school_id
            ], [
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

            // Verify user has permission (teacher or admin)
            if (!$request->user()->isSuperAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not authorized to clear chat'
                ], 403);
            }

            DB::beginTransaction();

            LiveChat::where('meeting_id', $meetingId)
                ->whereHas('meeting', function ($q) use ($request) {
                    $q->where('school_id', $request->school_id);
                })
                ->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'All chat messages cleared successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('LiveChat clearMeetingChat error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear chat messages'
            ], 500);
        }
    }
}