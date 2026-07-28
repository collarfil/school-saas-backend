<?php

namespace App\Modules\Onlinelearning\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Onlinelearning\Models\Recording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecordingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'meeting_id' => 'required|exists:meetings,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $recordings = Recording::where('meeting_id', $request->meeting_id)
                ->whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['meeting', 'createdBy'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $recordings
            ]);
        } catch (\Exception $e) {
            Log::error('Recording index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch recordings'
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:meeting_recordings,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $recording = Recording::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['meeting', 'createdBy'])
                ->find($id);

            if (!$recording) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Recording not found or does not belong to this school'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $recording
            ]);
        } catch (\Exception $e) {
            Log::error('Recording show error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meeting_id' => 'required|exists:meetings,id',
            'school_id' => 'required|exists:schools,id',
            'file_name' => 'required|string|max:255',
            'file_url' => 'required|string',
            'duration' => 'nullable|integer|min:0',
            'size' => 'nullable|integer|min:0',
            'provider' => 'required|string|max:255',
            'visibility' => 'nullable|in:public,private,unlisted'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Verify meeting belongs to school
            $meeting = \App\Modules\Onlinelearning\Models\Meeting::where('id', $request->meeting_id)
                ->where('school_id', $request->school_id)
                ->first();

            if (!$meeting) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Meeting not found or does not belong to this school'
                ], 404);
            }

            $recording = Recording::create([
                'meeting_id' => $request->meeting_id,
                'school_id' => $request->school_id,
                'file_name' => $request->file_name,
                'file_url' => $request->file_url,
                'duration' => $request->duration ?? 0,
                'size' => $request->size ?? 0,
                'provider' => $request->provider,
                'visibility' => $request->visibility ?? 'private',
                'created_by' => $request->user()->id
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Recording created successfully',
                'data' => $recording->load(['meeting', 'createdBy'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Recording store error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create recording: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:meeting_recordings,id',
            'school_id' => 'required|exists:schools,id',
            'file_name' => 'sometimes|string|max:255',
            'file_url' => 'sometimes|string',
            'duration' => 'sometimes|integer|min:0',
            'size' => 'sometimes|integer|min:0',
            'visibility' => 'sometimes|in:public,private,unlisted'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $recording = Recording::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$recording) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Recording not found or does not belong to this school'
                ], 404);
            }

            $recording->update($request->only([
                'file_name', 'file_url', 'duration', 'size', 'visibility'
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Recording updated successfully',
                'data' => $recording->fresh(['meeting', 'createdBy'])
            ]);

        } catch (\Exception $e) {
            Log::error('Recording update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update recording'
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:meeting_recordings,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $recording = Recording::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$recording) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Recording not found or does not belong to this school'
                ], 404);
            }

            $recording->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Recording deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Recording destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete recording'
            ], 500);
        }
    }

    public function updateVisibility(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:meeting_recordings,id',
            'school_id' => 'required|exists:schools,id',
            'visibility' => 'required|in:public,private,unlisted'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $recording = Recording::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$recording) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Recording not found or does not belong to this school'
                ], 404);
            }

            $recording->update(['visibility' => $request->visibility]);

            return response()->json([
                'status' => 'success',
                'message' => 'Visibility updated successfully',
                'data' => $recording
            ]);

        } catch (\Exception $e) {
            Log::error('Recording updateVisibility error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update visibility'
            ], 500);
        }
    }
}