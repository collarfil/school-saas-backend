<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppMessageJob;
use Illuminate\Http\Request;


class WhatsAppController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'to_number' => 'required|string',
            'message' => 'required|string',
        ]);
        
        SendWhatsAppMessageJob::dispatch($request->to_number, $request->message);

        return response()->json([
            'status' => 'queued',
            'message' => 'WhatsApp message is being sent'
        ]);
    }
}
