<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Events\StreamSignal;
use App\Http\Controllers\Controller;

class VideoController extends Controller
{
    public function signal(Request $request)
    {
        $request->validate([
            'user_to_call' => 'required|integer',
            'signal_data' => 'required',
            'from_id' => 'required|integer'
        ]);

        broadcast(new StreamSignal(
            $request->user_to_call,
            $request->signal_data,
            $request->from_id
        ));

        return response()->json(['status' => 'signal sent']);
    }
}
