<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SchoolSessionController extends Controller
{
    // GET: List all school sessions
    // App\Http\Controllers\Api\SchoolSessionController.php
public function index()
{
    try {
        // Get the authenticated user
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated',
                'data' => []
            ], 401);
        }
        
        // Get school ID from user
        $schoolId = $user->school_id;
        
        if (!$schoolId) {
            return response()->json([
                'status' => 'error',
                'message' => 'User has no school associated',
                'data' => []
            ], 400);
        }
        
        // Get sessions for this school
        $sessions = SchoolSession::where('school_id', $schoolId)
                    ->orderBy('is_current', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->get();
        
        // Log for debugging
        \Log::info('School sessions retrieved', [
            'user_id' => $user->id,
            'school_id' => $schoolId,
            'session_count' => $sessions->count()
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'School sessions retrieved successfully',
            'data' => $sessions
        ]);
        
    } catch (\Exception $e) {
        \Log::error('SchoolSessionController@index error: ' . $e->getMessage());
        
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to retrieve sessions',
            'error' => $e->getMessage(),
            'data' => []
        ], 500);
    }
}

    // POST: Create a new school session
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Check if user can manage academic records
            if (!$user->hasPermission('academic.manage') && !$user->isSuperAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have permission to create school sessions'
                ], 403);
            }

            // REMOVED: start_date and end_date validation
            $request->validate([
                'name' => 'required|string|max:255',
                'term' => 'required|string|max:100',
                'is_current' => 'boolean'
            ]);

            // For non-super admin users, ensure school_id is set
            $schoolId = $user->school_id;
            if (!$user->isSuperAdmin() && !$schoolId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No school associated with your account'
                ], 403);
            }

            // If marking as current, unset any existing current session for this school
            if ($request->is_current) {
                SchoolSession::where('is_current', true)
                    ->where('school_id', $schoolId)
                    ->update(['is_current' => false]);
            }

            // REMOVED: start_date and end_date from session data
            $sessionData = $request->only(['name', 'term', 'is_current']);
            $sessionData['school_id'] = $schoolId;
            
            $session = SchoolSession::create($sessionData);
            
            Log::info('School session created', [
                'user_id' => $user->id,
                'session_id' => $session->id,
                'school_id' => $schoolId
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'School session created successfully',
                'data' => $session
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('SchoolSessionController@store error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create school session',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // GET: Show a specific school session
    public function show($id)
    {
        try {
            $user = Auth::user();
            $session = SchoolSession::findOrFail($id);
            
            // Check if user has access to this session
            if (!$user->isSuperAdmin() && $session->school_id != $user->school_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this session'
                ], 403);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Session retrieved successfully',
                'data' => $session
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'School session not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('SchoolSessionController@show error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'session_id' => $id
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve school session',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // PUT/PATCH: Update a school session
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            
            // Check if user can manage academic records
            if (!$user->hasPermission('academic.manage') && !$user->isSuperAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have permission to update school sessions'
                ], 403);
            }

            $session = SchoolSession::findOrFail($id);
            
            // Check if user has access to this session
            if (!$user->isSuperAdmin() && $session->school_id != $user->school_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to update this session'
                ], 403);
            }

            // REMOVED: start_date and end_date validation
            $request->validate([
                'name' => 'required|string|max:255|unique:school_sessions,name,' . $session->id . ',id,school_id,' . $session->school_id,
                'term' => 'required|string|max:100',
                'is_current' => 'boolean'
            ]);

            // If marking as current, unset any existing current session for this school
            if ($request->is_current && !$session->is_current) {
                SchoolSession::where('is_current', true)
                    ->where('school_id', $session->school_id)
                    ->where('id', '!=', $session->id)
                    ->update(['is_current' => false]);
            }

            // REMOVED: start_date and end_date from update
            $session->update($request->only(['name', 'term', 'is_current']));
            
            Log::info('School session updated', [
                'user_id' => $user->id,
                'session_id' => $session->id,
                'school_id' => $session->school_id
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'School session updated successfully',
                'data' => $session
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'School session not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('SchoolSessionController@update error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'session_id' => $id,
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update school session',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // DELETE: Remove a school session
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            
            // Check if user can manage academic records
            if (!$user->hasPermission('academic.manage') && !$user->isSuperAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have permission to delete school sessions'
                ], 403);
            }

            $session = SchoolSession::findOrFail($id);
            
            // Check if user has access to this session
            if (!$user->isSuperAdmin() && $session->school_id != $user->school_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to delete this session'
                ], 403);
            }

            // Check if this is the current session
            if ($session->is_current) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete the current active session. Please set another session as current first.'
                ], 400);
            }

            // Check if session has associated data (you can add checks here later)
            
            $session->delete();
            
            Log::info('School session deleted', [
                'user_id' => $user->id,
                'session_id' => $id,
                'school_id' => $session->school_id
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'School session deleted successfully'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'School session not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('SchoolSessionController@destroy error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'session_id' => $id
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete school session',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // GET: Set a session as current
    public function setCurrent($id)
    {
        try {
            $user = Auth::user();
            
            // Check if user can manage academic records
            if (!$user->hasPermission('academic.manage') && !$user->isSuperAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have permission to set current session'
                ], 403);
            }

            $session = SchoolSession::findOrFail($id);
            
            // Check if user has access to this session
            if (!$user->isSuperAdmin() && $session->school_id != $user->school_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this session'
                ], 403);
            }

            // Unset any existing current session for this school
            SchoolSession::where('is_current', true)
                ->where('school_id', $session->school_id)
                ->update(['is_current' => false]);

            // Set this session as current
            $session->update(['is_current' => true]);
            
            Log::info('School session set as current', [
                'user_id' => $user->id,
                'session_id' => $session->id,
                'school_id' => $session->school_id
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Session set as current successfully',
                'data' => $session
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'School session not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('SchoolSessionController@setCurrent error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'session_id' => $id
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to set session as current',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}