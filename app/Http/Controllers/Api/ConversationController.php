<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // Get all conversations the user is participating in
        $conversations = Conversation::whereHas('participants', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with(['participants.user'])->get();
        
        return response()->json($conversations);
    }
}