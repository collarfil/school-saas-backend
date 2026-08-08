<?php

namespace App\Modules\Academics\Controllers\Api;

use App\Http\Controllers\Controller;
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
    public function show(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:timetables,id',
            'school_id' => 'required|exists:schools,id'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $timetable = TimeTable::where('id', $id)
            ->with(['school_session', 'school', 'grades', 'day', 'duration', 'subjects'])
            ->first();
        return response()->json([
            'status' => 'success',
            'data' => $timetable
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'day_id' => 'required|exists:days,id',
            'duration_id' => 'required|exists:durations,id',
            'school_session_id' => 'required|exists:school_sessions,id',
            'teacher_id' => 'required|exists:employees,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $timetable = TimeTable::create($request->all());
        return response()->json([
            'status' => 'success',
            'data' => $timetable
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:timetables,id',
            'school_id' => 'required|exists:schools,id',
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'day_id' => 'required|exists:days,id',
            'duration_id' => 'required|exists:durations,id',
            'school_session_id' => 'required|exists:school_sessions,id',
            'teacher_id' => 'required|exists:employees,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $timetable = TimeTable::where('id', $id)->update($request->all());
        return response()->json([
            'status' => 'success',
            'data' => $timetable
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:timetables,id',
            'school_id' => 'required|exists:schools,id'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $timetable = TimeTable::where('id', $id)->delete();
        return response()->json([
            'status' => 'success',
            'data' => $timetable
        ]);
    }

}