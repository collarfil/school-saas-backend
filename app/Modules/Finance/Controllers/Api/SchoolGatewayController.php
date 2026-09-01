<?php
// app/Modules/Finance/Controllers/Api/SchoolGatewayController.php

namespace App\Modules\Finance\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\SchoolGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SchoolGatewayController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $gateways = SchoolGateway::where('school_id', $request->school_id)
            ->select(['id', 'school_id', 'provider', 'is_active', 'created_at'])
            ->get();

        return response()->json(['status' => 'success', 'data' => $gateways]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'provider' => 'required|string|in:paystack,stripe,flutterwave',
            'api_public_key' => 'required|string',
            'api_secret_key' => 'required|string',
            'webhook_secret' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        try {
            // The model will automatically encrypt the keys via accessors
            $gateway = SchoolGateway::updateOrCreate(
                ['school_id' => $request->school_id, 'provider' => $request->provider],
                $validator->validated()
            );

            return response()->json([
                'status' => 'success', 
                'message' => 'Gateway keys stored securely', 
                'data' => [
                    'id' => $gateway->id,
                    'school_id' => $gateway->school_id,
                    'provider' => $gateway->provider,
                    'is_active' => $gateway->is_active,
                    'created_at' => $gateway->created_at,
                ]
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Failed to store gateway: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to store gateway credentials: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update gateway active status
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $gateway = SchoolGateway::find($id);
        
        if (!$gateway) {
            return response()->json(['status' => 'error', 'message' => 'Gateway not found'], 404);
        }

        $gateway->is_active = $request->is_active;
        $gateway->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Gateway status updated',
            'data' => $gateway
        ]);
    }

    /**
     * Delete a gateway configuration
     */
    public function destroy($id)
    {
        $gateway = SchoolGateway::find($id);
        
        if (!$gateway) {
            return response()->json(['status' => 'error', 'message' => 'Gateway not found'], 404);
        }

        $gateway->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Gateway configuration deleted'
        ]);
    }
}