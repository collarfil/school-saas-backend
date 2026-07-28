<?php

namespace App\Modules\Onlinelearning\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Onlinelearning\Models\Meeting;
use App\Modules\Onlinelearning\Models\MeetingParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MeetingParticipantController extends Controller
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

            $participants = MeetingParticipant::where('meeting_id', $request->meeting_id)
                ->whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['user', 'meeting'])
                ->orderBy('joined_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $participants
            ]);

        } catch (\Exception $e) {
            Log::error('MeetingParticipant index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch meeting participants'
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
                'id' => 'required|exists:meeting_participants,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $participant = MeetingParticipant::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['user', 'meeting'])
                ->find($id);

            if (!$participant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Participant not found or does not belong to this school'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $participant
            ]);

        } catch (\Exception $e) {
            Log::error('MeetingParticipant show error: ' . $e->getMessage());
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
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:host,co-host,participant,viewer',
            'joined_at' => 'nullable|date',
            'left_at' => 'nullable|date|after:joined_at',
            'attendance_duration' => 'nullable|integer|min:0',
            'camera_enabled' => 'nullable|boolean',
            'microphone_enabled' => 'nullable|boolean',
            'screen_shared' => 'nullable|boolean',
            'hand_raised' => 'nullable|boolean',
            'is_active' => 'nullable|boolean'
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

            // Check if participant already exists
            $existing = MeetingParticipant::where('meeting_id', $request->meeting_id)
                ->where('user_id', $request->user_id)
                ->first();

            if ($existing) {
                // Update existing participant
                $data = $request->except(['meeting_id', 'user_id']);

                if ($request->has('joined_at') && $request->joined_at) {
                    $data['joined_at'] = Carbon::parse($request->joined_at);
                } else {
                    $data['joined_at'] = Carbon::now();
                }

                if ($request->has('left_at') && $request->left_at) {
                    $data['left_at'] = Carbon::parse($request->left_at);
                }

                $existing->update($data);

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Participant updated successfully',
                    'data' => $existing->fresh(['user', 'meeting'])
                ]);
            }

            // Create new participant
            $participant = MeetingParticipant::create([
                'meeting_id' => $request->meeting_id,
                'user_id' => $request->user_id,
                'role' => $request->role,
                'joined_at' => $request->joined_at ? Carbon::parse($request->joined_at) : Carbon::now(),
                'left_at' => $request->left_at ? Carbon::parse($request->left_at) : null,
                'attendance_duration' => $request->attendance_duration ?? 0,
                'camera_enabled' => $request->camera_enabled ?? false,
                'microphone_enabled' => $request->microphone_enabled ?? false,
                'screen_shared' => $request->screen_shared ?? false,
                'hand_raised' => $request->hand_raised ?? false,
                'is_active' => $request->is_active ?? true
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Participant joined successfully',
                'data' => $participant->load(['user', 'meeting'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MeetingParticipant store error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add participant: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:meeting_participants,id',
            'school_id' => 'required|exists:schools,id',
            'role' => 'sometimes|in:host,co-host,participant,viewer',
            'joined_at' => 'nullable|date',
            'left_at' => 'nullable|date|after:joined_at',
            'attendance_duration' => 'nullable|integer|min:0',
            'camera_enabled' => 'nullable|boolean',
            'microphone_enabled' => 'nullable|boolean',
            'screen_shared' => 'nullable|boolean',
            'hand_raised' => 'nullable|boolean',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $participant = MeetingParticipant::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$participant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Participant not found or does not belong to this school'
                ], 404);
            }

            $data = $request->except(['school_id', 'meeting_id', 'user_id']);

            if ($request->has('joined_at') && $request->joined_at) {
                $data['joined_at'] = Carbon::parse($request->joined_at);
            }

            if ($request->has('left_at') && $request->left_at) {
                $data['left_at'] = Carbon::parse($request->left_at);
            }

            $participant->update($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Participant updated successfully',
                'data' => $participant->fresh(['user', 'meeting'])
            ]);

        } catch (\Exception $e) {
            Log::error('MeetingParticipant update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update participant'
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
                'id' => 'required|exists:meeting_participants,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $participant = MeetingParticipant::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$participant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Participant not found or does not belong to this school'
                ], 404);
            }

            $participant->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Participant removed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('MeetingParticipant destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove participant'
            ], 500);
        }
    }

    public function leaveMeeting(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:meeting_participants,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $participant = MeetingParticipant::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$participant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Participant not found or does not belong to this school'
                ], 404);
            }

            $now = Carbon::now();

            // Calculate duration if joined_at exists
            $duration = 0;
            if ($participant->joined_at) {
                $duration = Carbon::parse($participant->joined_at)->diffInSeconds($now);
            }

            $participant->update([
                'left_at' => $now,
                'attendance_duration' => $duration,
                'is_active' => false
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Participant left meeting successfully',
                'data' => $participant->fresh(['user', 'meeting'])
            ]);

        } catch (\Exception $e) {
            Log::error('MeetingParticipant leaveMeeting error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process leave meeting'
            ], 500);
        }
    }

    public function getMeetingParticipants(Request $request, $meetingId)
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

            $participants = MeetingParticipant::where('meeting_id', $meetingId)
                ->whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['user'])
                ->orderBy('role', 'asc')
                ->orderBy('joined_at', 'asc')
                ->get();

            // Group by role for summary
            $summary = [
                'total' => $participants->count(),
                'hosts' => $participants->where('role', 'host')->count(),
                'co_hosts' => $participants->where('role', 'co-host')->count(),
                'participants' => $participants->where('role', 'participant')->count(),
                'viewers' => $participants->where('role', 'viewer')->count(),
                'active' => $participants->where('is_active', true)->count()
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'participants' => $participants,
                    'summary' => $summary
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('MeetingParticipant getMeetingParticipants error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch meeting participants'
            ], 500);
        }
    }

    public function getParticipantAttendance(Request $request, $meetingId)
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

            $participants = MeetingParticipant::where('meeting_id', $meetingId)
                ->whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['user'])
                ->where('is_active', false)
                ->whereNotNull('left_at')
                ->orderBy('attendance_duration', 'desc')
                ->get();

            $totalDuration = $participants->sum('attendance_duration');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'participants' => $participants,
                    'total_duration_seconds' => $totalDuration,
                    'total_duration_formatted' => $this->formatDuration($totalDuration),
                    'average_duration' => $participants->count() > 0 ? $totalDuration / $participants->count() : 0
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('MeetingParticipant getParticipantAttendance error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch participant attendance'
            ], 500);
        }
    }

    public function toggleHandRaise(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:meeting_participants,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $participant = MeetingParticipant::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$participant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Participant not found or does not belong to this school'
                ], 404);
            }

            $participant->update([
                'hand_raised' => !$participant->hand_raised
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $participant->hand_raised ? 'Hand raised' : 'Hand lowered',
                'data' => $participant
            ]);

        } catch (\Exception $e) {
            Log::error('MeetingParticipant toggleHandRaise error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle hand raise'
            ], 500);
        }
    }

    public function updateParticipantStatus(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:meeting_participants,id',
            'school_id' => 'required|exists:schools,id',
            'camera_enabled' => 'nullable|boolean',
            'microphone_enabled' => 'nullable|boolean',
            'screen_shared' => 'nullable|boolean',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $participant = MeetingParticipant::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$participant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Participant not found or does not belong to this school'
                ], 404);
            }

            $participant->update($request->only([
                'camera_enabled', 'microphone_enabled', 'screen_shared', 'is_active'
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Participant status updated successfully',
                'data' => $participant
            ]);

        } catch (\Exception $e) {
            Log::error('MeetingParticipant updateParticipantStatus error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update participant status'
            ], 500);
        }
    }

    private function formatDuration($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }
        return sprintf('%02d:%02d', $minutes, $secs);
    }
}