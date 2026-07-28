<?php

namespace App\Modules\Academics\Controllers\Api;

use App\Modules\Academics\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
class TimeTableController extends Controller
{
    public function index(Request $request)
    {
        $Validator = Validator::make($request->all(),[
            'school_id' => 'required|exists:schools,id'
        ]);
         if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
         }

          $timetable = TimeTable::where('school_id', $request->school_id)
            ->with(['school_session', 'school', 'grades', 'day', 'duration', 'subjects'])
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $timetable
        ]);
    }
    public function show()
    {

    }

    public function store()
    {

    }

    public function update()
    {

    }

    public function destroy()
    {

    }

}