<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\Grade;
use App\Models\SchoolSession;
use Illuminate\Http\Request;

class ResultLockController extends Controller
{
    public function index()
    {
        try {
            \Log::info('ResultLockController index called');
            
            // Get unique combinations of grade, term, session with their lock status
            $lockedResults = Result::select('grade_id', 'term', 'school_session_id', 'is_locked')
                ->distinct()
                ->with(['grade:id,name', 'schoolSession:id,name'])
                ->get()
                ->map(function($result) {
                    return [
                        'id' => $result->school_session_id . '-' . $result->term . '-' . $result->grade_id,
                        'grade_id' => $result->grade_id,
                        'grade_name' => $result->grade->name ?? 'N/A',
                        'term' => $result->term,
                        'school_session_id' => $result->school_session_id,
                        'session_name' => $result->schoolSession->name ?? 'N/A',
                        'is_locked' => (bool) $result->is_locked,
                    ];
                });

            return response()->json($lockedResults);
            
        } catch (\Exception $e) {
            \Log::error('ResultLockController error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function options()
    {
        try {
            $grades = Grade::select('id', 'name')->get();
            $sessions = SchoolSession::select('id', 'name')->get();
            $terms = ['1st term', '2nd term', '3rd term'];

            return response()->json([
                'grades' => $grades,
                'sessions' => $sessions,
                'terms' => $terms
            ]);
        } catch (\Exception $e) {
            \Log::error('ResultLockController options error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function lock(Request $request)
    {
        try {
            $request->validate([
                'grade_id' => 'required|integer|exists:grades,id',
                'term' => 'required|string|in:1st term,2nd term,3rd term',
                'school_session_id' => 'required|integer|exists:school_sessions,id',
            ]);

            $updated = Result::where('grade_id', $request->grade_id)
                ->where('term', $request->term)
                ->where('school_session_id', $request->school_session_id)
                ->update(['is_locked' => true]);

            return response()->json([
                'message' => 'Results locked successfully',
                'updated_count' => $updated
            ]);
        } catch (\Exception $e) {
            \Log::error('ResultLockController lock error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function unlock(Request $request)
    {
        try {
            $request->validate([
                'grade_id' => 'required|integer|exists:grades,id',
                'term' => 'required|string|in:1st term,2nd term,3rd term',
                'school_session_id' => 'required|integer|exists:school_sessions,id',
            ]);

            $updated = Result::where('grade_id', $request->grade_id)
                ->where('term', $request->term)
                ->where('school_session_id', $request->school_session_id)
                ->update(['is_locked' => false]);

            return response()->json([
                'message' => 'Results unlocked successfully',
                'updated_count' => $updated
            ]);
        } catch (\Exception $e) {
            \Log::error('ResultLockController unlock error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}