<?php

use Illuminate\Support\Facades\Route;

use App\Modules\Onlinelearning\Controllers\Api\LiveClassController;
use App\Modules\Onlinelearning\Controllers\Api\MeetingController;
use App\Modules\Onlinelearning\Controllers\Api\MeetingRecordingController;
use App\Modules\Onlinelearning\Controllers\Api\MeetingParticipantController;
use App\Modules\Onlinelearning\Controllers\Api\MeetingAttendanceController;
use App\Modules\Onlinelearning\Controllers\Api\MeetingMessageController;
use App\Modules\Onlinelearning\Controllers\Api\LiveChatController;
use App\Modules\Onlinelearning\Controllers\Api\WhiteboardController;
use App\Modules\Onlinelearning\Controllers\Api\PollController;
use App\Modules\Onlinelearning\Controllers\Api\PollResponseController;
use App\Modules\Onlinelearning\Controllers\Api\AssignmentController;
use App\Modules\Onlinelearning\Controllers\Api\AssignmentSubmissionController;

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (JWT REQUIRED) - Online Learning
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth'])->prefix('v1')->group(function () {

    /*
    | LIVE CLASSES
    */
    Route::apiResource('live-classes', LiveClassController::class);
    Route::patch('live-classes/{id}/status', [LiveClassController::class, 'updateStatus']);
    Route::get('live-classes/upcoming', [LiveClassController::class, 'getUpcoming']);
    Route::get('live-classes/ongoing', [LiveClassController::class, 'getOngoing']);
    Route::get('live-classes/by-date-range', [LiveClassController::class, 'getByDateRange']);
    Route::get('live-classes/teachers/{employeeId}', [LiveClassController::class, 'getTeacherLiveClasses']);
    Route::get('live-classes/grades/{gradeId}', [LiveClassController::class, 'getGradeLiveClasses']);
    Route::get('live-classes/stats', [LiveClassController::class, 'getStats']);

    /*
    | MEETINGS
    */
    Route::apiResource('meetings', MeetingController::class);
    Route::post('meetings/{id}/start', [MeetingController::class, 'start']);
    Route::post('meetings/{id}/end', [MeetingController::class, 'end']);
    Route::get('meetings/upcoming', [MeetingController::class, 'getUpcoming']);
    Route::get('meetings/ongoing', [MeetingController::class, 'getOngoing']);
    Route::get('live-classes/{liveClassId}/meetings', [MeetingController::class, 'getByLiveClass']);

    /*
    | MEETING RECORDINGS
    */
    Route::apiResource('meeting-recordings', MeetingRecordingController::class);
    Route::patch('meeting-recordings/{id}/visibility', [MeetingRecordingController::class, 'updateVisibility']);

    /*
    | MEETING PARTICIPANTS
    */
    Route::apiResource('meeting-participants', MeetingParticipantController::class);
    Route::post('meeting-participants/{id}/leave', [MeetingParticipantController::class, 'leaveMeeting']);
    Route::get('meetings/{meetingId}/participants', [MeetingParticipantController::class, 'getMeetingParticipants']);
    Route::get('meetings/{meetingId}/attendance', [MeetingParticipantController::class, 'getParticipantAttendance']);
    Route::patch('meeting-participants/{id}/hand-raise', [MeetingParticipantController::class, 'toggleHandRaise']);
    Route::patch('meeting-participants/{id}/status', [MeetingParticipantController::class, 'updateParticipantStatus']);

    /*
    | MEETING ATTENDANCE
    */
    Route::apiResource('meeting-attendance', MeetingAttendanceController::class);
    Route::post('meetings/{meetingId}/students/{studentId}/present', [MeetingAttendanceController::class, 'markPresent']);
    Route::post('meetings/{meetingId}/students/{studentId}/absent', [MeetingAttendanceController::class, 'markAbsent']);
    Route::get('meetings/{meetingId}/attendance-summary', [MeetingAttendanceController::class, 'getMeetingAttendanceSummary']);

    /*
    | MEETING MESSAGES (Legacy - keep for backward compatibility)
    */
    Route::apiResource('meeting-messages', MeetingMessageController::class);
    Route::get('meeting-messages/{messageId}/thread', [MeetingMessageController::class, 'getThread']);
    Route::patch('meeting-messages/{id}/teacher', [MeetingMessageController::class, 'markAsTeacher']);

    /*
    | LIVE CHAT
    */
    Route::prefix('live-chat')->group(function () {
        Route::get('/', [LiveChatController::class, 'index']);
        Route::post('/', [LiveChatController::class, 'store']);
        Route::get('{id}', [LiveChatController::class, 'show']);
        Route::put('{id}', [LiveChatController::class, 'update']);
        Route::delete('{id}', [LiveChatController::class, 'destroy']);
        
        // Meeting specific
        Route::get('meeting/{meetingId}/messages', [LiveChatController::class, 'getMessagesByMeeting']);
        Route::get('meeting/{meetingId}/user/{userId}', [LiveChatController::class, 'getUserMessages']);
        Route::get('meeting/{meetingId}/latest', [LiveChatController::class, 'getLatest']);
        Route::get('meeting/{meetingId}/stats', [LiveChatController::class, 'getChatStats']);
        Route::delete('meeting/{meetingId}/clear', [LiveChatController::class, 'clearMeetingChat']);
        
        // Thread
        Route::get('{id}/thread', [LiveChatController::class, 'getThread']);
        
        // Teacher marking
        Route::patch('{id}/mark-teacher', [LiveChatController::class, 'markAsTeacher']);
    });

    /*
    | WHITEBOARDS
    */
    Route::apiResource('whiteboards', WhiteboardController::class);
    Route::get('meetings/{meetingId}/whiteboards/latest', [WhiteboardController::class, 'getLatest']);

    /*
    | POLLS
    */
    Route::apiResource('polls', PollController::class);
    Route::get('polls/{id}/results', [PollController::class, 'getResults']);

    /*
    | POLL RESPONSES
    */
    Route::apiResource('poll-responses', PollResponseController::class);
    Route::get('polls/{pollId}/students/{studentId}/response', [PollResponseController::class, 'getStudentResponse']);
    Route::get('polls/{pollId}/statistics', [PollResponseController::class, 'getPollStatistics']);

    /*
    | ASSIGNMENTS
    */
    Route::apiResource('assignments', AssignmentController::class);
    Route::patch('assignments/{id}/publish', [AssignmentController::class, 'publish']);
    Route::patch('assignments/{id}/close', [AssignmentController::class, 'close']);
    Route::get('live-classes/{liveClassId}/assignments', [AssignmentController::class, 'getAssignmentsByLiveClass']);
    Route::get('employees/{employeeId}/assignments', [AssignmentController::class, 'getAssignmentsByEmployee']);
    Route::get('assignments/stats', [AssignmentController::class, 'getStats']);

    /*
    | ASSIGNMENT SUBMISSIONS
    */
    Route::apiResource('assignment-submissions', AssignmentSubmissionController::class);
    Route::post('assignment-submissions/{id}/grade', [AssignmentSubmissionController::class, 'grade']);
    Route::get('students/{studentId}/submissions', [AssignmentSubmissionController::class, 'getStudentSubmissions']);
});