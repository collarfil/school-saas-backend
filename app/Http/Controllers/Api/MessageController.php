<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConversationParticipant;
use App\Models\Message;
use Illuminate\Http\Request;
use App\Events\MessageSent;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function index($conversationId)
    {
        try {
            $allowed = ConversationParticipant::where([
                'conversation_id' => $conversationId,
                'user_id' => auth()->user()->id,
            ])->exists();

            if (!$allowed) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Use 'user' instead of 'sender' in the with clause
            $messages = Message::where('conversation_id', $conversationId)
                ->with('user:id,name')
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json($messages);
        } catch (\Exception $e) {
            Log::error('Message index error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, $conversationId)
    {
        try {
            Log::info('Attempting to store message', [
                'conversation_id' => $conversationId,
                'user_id' => auth()->user()->id,
                'message' => $request->message
            ]);

            $request->validate([
                'message' => 'required|string'
            ]);

            $allowed = ConversationParticipant::where([
                'conversation_id' => $conversationId,
                'user_id' => auth()->user()->id,
            ])->exists();

            if (!$allowed) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Use 'user_id' instead of 'sender_id'
            $message = Message::create([
                'conversation_id' => $conversationId,
                'user_id' => auth()->user()->id,  // Changed from 'sender_id' to 'user_id'
                'body' => $request->message,
            ]);

            Log::info('Message created successfully', ['message_id' => $message->id]);

            // The event will need to be updated too
            broadcast(new MessageSent($message))->toOthers();

            // Use 'user' instead of 'sender' in the load
            return response()->json(
                $message->load('user:id,name')
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Message store error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}