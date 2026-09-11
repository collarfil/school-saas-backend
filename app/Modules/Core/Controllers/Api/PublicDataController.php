<?php

namespace App\Modules\Core\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PublicDataController extends Controller
{
    /**
     * Resolve the numeric school ID from either school_id or school_uuid.
     */
    private function resolveSchoolId(Request $request): ?int
    {
        if ($request->filled('school_id')) {
            return (int) $request->query('school_id');
        }

        if ($request->filled('school_uuid')) {
            return School::where('uuid', $request->query('school_uuid'))
                ->where('is_unlocked', true)
                ->value('id');
        }

        return null;
    }

    /**
     * GET /v1/public/grades?school_uuid=XYZ or ?school_id=1
     */
    public function grades(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);

        if (!$schoolId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'A valid school_id or school_uuid is required.',
            ], 422);
        }

        $grades = DB::table('grades')
            ->where('school_id', $schoolId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $grades,
        ]);
    }

    /**
     * GET /v1/public/school-sessions?school_uuid=XYZ or ?school_id=1
     */
    public function sessions(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);

        if (!$schoolId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'A valid school_id or school_uuid is required.',
            ], 422);
        }

        $sessions = DB::table('school_sessions')
            ->where('school_id', $schoolId)
            ->select('id', 'name', 'term', 'is_current', 'start_date', 'end_date')
            ->orderByDesc('is_current')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $sessions,
        ]);
    }
}