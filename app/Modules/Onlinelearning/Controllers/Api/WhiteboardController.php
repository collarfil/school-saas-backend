<?php

namespace App\Modules\Onlinelearning\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Onlinelearning\Models\Whiteboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhiteboardController extends Controller
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

            $whiteboards = Whiteboard::where('meeting_id', $request->meeting_id)
                ->whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['createdBy'])
                ->orderBy('page_number', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $whiteboards
            ]);

        } catch (\Exception $e) {
            Log::error('Whiteboard index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch whiteboards'
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
                'id' => 'required|exists:whiteboards,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $whiteboard = Whiteboard::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['createdBy'])
                ->find($id);

            if (!$whiteboard) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Whiteboard not found or does not belong to this school'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $whiteboard
            ]);

        } catch (\Exception $e) {
            Log::error('Whiteboard show error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meeting_id' => 'required|exists:meetings,id',
            'school_id' => 'required|exists:schools,id',
            'page_number' => 'nullable|integer|min:1',
            'board_data' => 'required|string'
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

            // If page_number not provided, get the next page number
            $pageNumber = $request->page_number;
            if (!$pageNumber) {
                $lastPage = Whiteboard::where('meeting_id', $request->meeting_id)
                    ->max('page_number') ?? 0;
                $pageNumber = $lastPage + 1;
            }

            $whiteboard = Whiteboard::create([
                'meeting_id' => $request->meeting_id,
                'page_number' => $pageNumber,
                'board_data' => $request->board_data,
                'created_by' => $request->user()->id
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Whiteboard saved successfully',
                'data' => $whiteboard->load(['createdBy'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Whiteboard store error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save whiteboard: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:whiteboards,id',
            'school_id' => 'required|exists:schools,id',
            'board_data' => 'sometimes|string',
            'page_number' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $whiteboard = Whiteboard::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$whiteboard) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Whiteboard not found or does not belong to this school'
                ], 404);
            }

            $data = $request->only(['board_data']);
            if ($request->has('page_number')) {
                $data['page_number'] = $request->page_number;
            }

            $whiteboard->update($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Whiteboard updated successfully',
                'data' => $whiteboard->fresh(['createdBy'])
            ]);

        } catch (\Exception $e) {
            Log::error('Whiteboard update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update whiteboard'
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
                'id' => 'required|exists:whiteboards,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $whiteboard = Whiteboard::whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->find($id);

            if (!$whiteboard) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Whiteboard not found or does not belong to this school'
                ], 404);
            }

            $whiteboard->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Whiteboard deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Whiteboard destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete whiteboard'
            ], 500);
        }
    }

    public function getLatest(Request $request, $meetingId)
    {
        try {
            $validator = Validator::make([
                'meeting_id' => $meetingId,
                'school_id' => $request->school_id
            ], [
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

            $whiteboard = Whiteboard::where('meeting_id', $meetingId)
                ->whereHas('meeting', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->with(['createdBy'])
                ->orderBy('page_number', 'desc')
                ->first();

            return response()->json([
                'status' => 'success',
                'data' => $whiteboard
            ]);

        } catch (\Exception $e) {
            Log::error('Whiteboard getLatest error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get latest whiteboard'
            ], 500);
        }
    }
}