<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ConversationParticipant;
use App\Models\Conversation;


Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {

//     return ConversationParticipant::where([
//         'conversation_id' => $conversationId,
//         'user_id' => $user->id,
//     ])->exists();

// });

// 1. Conversation Channel (For Messages)
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    // Check if conversation belongs to user's school and user is a participant
    return ConversationParticipant::where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->whereHas('conversation', function($q) use ($user) {
            $q->where('school_id', $user->school_id);
        })->exists();
});

// 2. Private User Channel (For Video Signaling Handshakes)
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// 3. Presence Channel (For Online "Classroom" Attendance)
Broadcast::channel('classroom.{conversationId}', function ($user, $conversationId) {
    $isParticipant = ConversationParticipant::where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->exists();

    if ($isParticipant) {
        return ['id' => $user->id, 'name' => $user->name, 'role' => $user->role];
    }
    return false;
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return \App\Models\ConversationParticipant::where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('classroom.{conversationId}', function ($user, $conversationId) {
    $isParticipant = \App\Models\ConversationParticipant::where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->exists();

    if ($isParticipant) {
        return ['id' => $user->id, 'name' => $user->name, 'role' => $user->role];
    }
    return false;
});