<?php

namespace App\Modules\Academics\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\TimeTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TimeTableController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'school_id is required',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = TimeTable::where('school_id', $request->school_id)
            ->with(['schoolSession', 'grade', 'subject', 'school']);

        if ($request->filled('school_session_id')) {
            $query->where('school_session_id', $request->school_session_id);
        }

        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->filled('day')) {
            $query->where('day', $request->day);
        }

        $timetables = $query->orderBy('day')->latest()->paginate(25);

        return response()->json([
            'status' => 'success',
            'data' => $timetables
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required',
            'school_session_id' => 'required',
            'grade_id' => 'required',
            'subject_id' => 'required',
            'day' => 'required|string',
            'period' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $timetable = TimeTable::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Timetable slot created successfully',
            'data' => $timetable->load(['schoolSession', 'grade', 'subject'])
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $timetable = TimeTable::where('school_id', $request->school_id)
            ->with(['schoolSession', 'grade', 'subject'])
            ->find($id);

        if (!$timetable) {
            return response()->json(['status' => 'error', 'message' => 'Slot not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $timetable]);
    }

    public function update(Request $request, $id)
    {
        $timetable = TimeTable::where('school_id', $request->school_id)->find($id);

        if (!$timetable) {
            return response()->json(['status' => 'error', 'message' => 'Slot not found'], 404);
        }

        $timetable->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Timetable slot updated successfully',
            'data' => $timetable->fresh(['schoolSession', 'grade', 'subject'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $timetable = TimeTable::where('school_id', $request->school_id)->find($id);

        if (!$timetable) {
            return response()->json(['status' => 'error', 'message' => 'Slot not found'], 404);
        }

        $timetable->delete();

        return response()->json(['status' => 'success', 'message' => 'Slot deleted successfully']);
    }
}