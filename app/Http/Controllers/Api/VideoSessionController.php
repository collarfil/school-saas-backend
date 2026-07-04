<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VideoSession;
use App\Models\VideoParticipant;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class VideoSessionController extends Controller
{
    // Get all video sessions for a class
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|exists:grades,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $sessions = VideoSession::where('class_id', $request->class_id)
            ->where('school_id', $request->school_id)
            ->with(['teacher', 'participants.user'])
            ->orderBy('start_time', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $sessions
        ]);
    }

    // Create a new video session (teacher only)
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|exists:grades,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'duration' => 'nullable|integer|min:15|max:180',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();
        
        // Check if user is teacher or admin
        if (!$user->isEmployee() && !$user->isSchoolAdmin() && !$user->isSuperAdmin()) {
            return response()->json(['error' => 'Only teachers can create video sessions'], 403);
        }

        // Generate unique meeting ID
        $meetingId = 'CLASS-' . strtoupper(Str::random(10));
        $meetingPassword = rand(100000, 999999);

        $endTime = null;
        if ($request->duration) {
            $endTime = now()->parse($request->start_time)->addMinutes($request->duration);
        }

        $session = VideoSession::create([
            'class_id' => $request->class_id,
            'teacher_id' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'start_time' => $request->start_time,
            'end_time' => $endTime,
            'meeting_id' => $meetingId,
            'meeting_password' => (string)$meetingPassword,
            'status' => 'scheduled',
            'school_id' => $request->school_id
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Video session created successfully',
            'data' => $session->load('teacher')
        ], 201);
    }

    // Join a video session
    public function join(Request $request, $id)
    {
        $session = VideoSession::findOrFail($id);
        $user = auth()->user();

        // Check if user is allowed to join
        $isTeacher = $session->teacher_id == $user->id;
        $isAdmin = $user->isSchoolAdmin() || $user->isSuperAdmin();
        
        // Check if student is in this class
        $isStudent = false;
        if ($user->isStudent()) {
            $student = $user->student;
            $isStudent = $student && $student->grade_id == $session->class_id;
        }

        if (!$isTeacher && !$isAdmin && !$isStudent) {
            return response()->json(['error' => 'You are not authorized to join this session'], 403);
        }

        // Update session status to active if it's start time
        if ($session->status == 'scheduled' && now()->gte($session->start_time)) {
            $session->update(['status' => 'active']);
        }

        // Record participant
        $participant = VideoParticipant::updateOrCreate(
            [
                'video_session_id' => $session->id,
                'user_id' => $user->id
            ],
            [
                'joined_at' => now(),
                'is_active' => true
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'session' => $session,
                'meeting_id' => $session->meeting_id,
                'meeting_password' => $session->meeting_password,
                'participant' => $participant
            ]
        ]);
    }

    // Leave a video session
    public function leave(Request $request, $id)
    {
        $session = VideoSession::findOrFail($id);
        $user = auth()->user();

        $participant = VideoParticipant::where('video_session_id', $session->id)
            ->where('user_id', $user->id)
            ->first();

        if ($participant) {
            $participant->update([
                'left_at' => now(),
                'is_active' => false,
                'duration' => $participant->joined_at ? now()->diffInSeconds($participant->joined_at) : null
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Left the session'
        ]);
    }

    // End a video session (teacher only)
    public function end(Request $request, $id)
    {
        $session = VideoSession::findOrFail($id);
        $user = auth()->user();

        if ($session->teacher_id != $user->id && !$user->isSchoolAdmin()) {
            return response()->json(['error' => 'Only the teacher can end this session'], 403);
        }

        $session->update([
            'status' => 'ended',
            'end_time' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Session ended'
        ]);
    }

    // Get active sessions for a user
    public function myActiveSessions(Request $request)
    {
        $user = auth()->user();
        $schoolId = $request->school_id;

        $query = VideoSession::where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('start_time', '<=', now())
            ->where(function($q) use ($user) {
                $q->where('teacher_id', $user->id);
                
                if ($user->isStudent()) {
                    $q->orWhere('class_id', $user->student->grade_id ?? 0);
                }
            });

        $sessions = $query->with(['teacher', 'class'])
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $sessions
        ]);
    }
}