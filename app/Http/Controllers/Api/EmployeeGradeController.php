<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeGrade;
use Illuminate\Http\Request;

class EmployeeGradeController extends Controller
{
    public function index()
    {
        return response()->json(EmployeeGrade::with(['employee', 'grade'])->paginate(25));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'grade_id'    => 'required|exists:grades,id',
        ]);

        $mapping = EmployeeGrade::create($validated);
        return response()->json(['message' => 'Employee-Grade mapping created', 'data' => $mapping], 201);
    }

    public function destroy($id)
    {
        EmployeeGrade::findOrFail($id)->delete();
        return response()->json(['message' => 'Mapping deleted']);
    }
}
