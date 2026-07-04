<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class WhatsAppController extends Controller
{
    // Send WhatsApp message
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to_number' => 'required|string',
            'message' => 'required|string',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();

        // Save message to database
        $whatsappMessage = WhatsAppMessage::create([
            'school_id' => $request->school_id,
            'to_number' => $request->to_number,
            'message' => $request->message,
            'status' => 'pending'
        ]);

        // Here you would integrate with WhatsApp Business API
        // For now, we'll just log it
        Log::info('WhatsApp message queued', [
            'message_id' => $whatsappMessage->id,
            'to' => $request->to_number,
            'from_user' => $user->id
        ]);

        // Simulate sending (replace with actual API call)
        try {
            // Example with Twilio or WhatsApp Business API
            // $response = Http::withHeaders([
            //     'Authorization' => 'Bearer ' . config('services.whatsapp.token')
            // ])->post('https://graph.facebook.com/v17.0/YOUR_PHONE_NUMBER_ID/messages', [
            //     'messaging_product' => 'whatsapp',
            //     'to' => $request->to_number,
            //     'type' => 'text',
            //     'text' => ['body' => $request->message]
            // ]);

            // For now, mark as sent
            $whatsappMessage->update(['status' => 'sent']);
            
            return response()->json([
                'status' => 'success',
                'message' => 'WhatsApp message sent successfully',
                'data' => $whatsappMessage
            ]);

        } catch (\Exception $e) {
            $whatsappMessage->update(['status' => 'failed']);
            Log::error('WhatsApp send error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send WhatsApp message'
            ], 500);
        }
    }

    // Get WhatsApp messages for a school
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $messages = WhatsAppMessage::where('school_id', $request->school_id)
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return response()->json([
            'status' => 'success',
            'data' => $messages
        ]);
    }
}