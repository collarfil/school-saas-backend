<?php

namespace App\Modules\Core\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Admission;
use App\Modules\Core\Models\School;
use App\Modules\Academics\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdmissionController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Admission::where('school_id', $request->school_id)
            ->with(['grade', 'school']);

        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $admissions = $query->paginate(50);

        return response()->json([
            'status' => 'success',
            'data' => $admissions
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'grade_id' => 'required|exists:grades,id',
            'prev_grade' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'gender' => 'required|string|in:male,female,other',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $admission = Admission::create([
            'school_id' => $request->school_id,
            'grade_id' => $request->grade_id,
            'prev_grade' => $request->prev_grade,
            'name' => $request->name,
            'gender' => $request->gender,
            'phone' => $request->phone,
            'address' => $request->address,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Admission application created successfully',
            'data' => $admission->load(['grade', 'school'])
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:admission,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $admission = Admission::where('school_id', $request->school_id)
            ->with(['grade', 'school'])
            ->find($id);

        if (!$admission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admission record not found or does not belong to this school.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $admission
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:admission,id',
            'school_id' => 'required|exists:schools,id',
            'grade_id' => 'sometimes|required|exists:grades,id',
            'prev_grade' => 'sometimes|required|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'gender' => 'sometimes|required|string|in:male,female,other',
            'phone' => 'sometimes|required|string|max:20',
            'address' => 'sometimes|required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $admission = Admission::where('school_id', $request->school_id)->find($id);

        if (!$admission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admission record not found or does not belong to this school.'
            ], 404);
        }

        $admission->update($request->only([
            'grade_id',
            'prev_grade',
            'name',
            'gender',
            'phone',
            'address',
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Admission record updated successfully',
            'data' => $admission->fresh(['grade', 'school'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:admission,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $admission = Admission::where('school_id', $request->school_id)->find($id);

        if (!$admission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admission record not found or does not belong to this school.'
            ], 404);
        }

        $admission->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Admission record deleted successfully'
        ]);
    }
}