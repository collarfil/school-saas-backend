<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

use App\Modules\Communication\Controllers\Api\MessageController;
use App\Modules\Communication\Controllers\Api\ConversationController;
use App\Modules\Communication\Controllers\Api\VideoController;
use App\Modules\Communication\Controllers\Api\VideoSessionController;
use App\Modules\Communication\Controllers\Api\WhatsAppController;

/*
|--------------------------------------------------------------------------
| BROADCAST CHANNEL AUTH
|--------------------------------------------------------------------------
*/
Broadcast::channel('conversation.{id}', function ($user, $id) {
    return \App\Modules\Communication\Models\ConversationParticipant::where('conversation_id', $id)
        ->where('user_id', $user->id)
        ->exists();
});

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (NO AUTH) - Communication
|--------------------------------------------------------------------------
*/
Route::get('/test-broadcast', function () {
    broadcast(new \App\Events\TestBroadcast("Hello Reverb"));
    return "Event Fired";
});

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (JWT REQUIRED) - Communication
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth'])->prefix('v1')->group(function () {

    /*
    | BROADCASTING AUTH
    */
    Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
        return Broadcast::auth($request);
    });

    /*
    | CONVERSATIONS
    */
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{conversationId}/messages', [MessageController::class, 'index']);
    Route::post('/conversations/{conversationId}/messages', [MessageController::class, 'store']);

    /*
    | VIDEO SIGNALING
    */
    Route::post('/video/signal', [VideoController::class, 'signal']);

    /*
    | VIDEO SESSIONS
    */
    Route::get('/video-sessions', [VideoSessionController::class, 'index']);
    Route::post('/video-sessions', [VideoSessionController::class, 'store']);
    Route::post('/video-sessions/{id}/join', [VideoSessionController::class, 'join']);
    Route::post('/video-sessions/{id}/leave', [VideoSessionController::class, 'leave']);
    Route::post('/video-sessions/{id}/end', [VideoSessionController::class, 'end']);
    Route::get('/video-sessions/my-active', [VideoSessionController::class, 'myActiveSessions']);

    /*
    | WHATSAPP
    */
    Route::post('/whatsapp/send', [WhatsAppController::class, 'send']);
    Route::get('/whatsapp/messages', [WhatsAppController::class, 'index']);
});