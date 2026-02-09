<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSubject;
use Illuminate\Http\Request;

class EmployeeSubjectController extends Controller
{
    public function index()
    {
        return response()->json(EmployeeSubject::with(['employee', 'subject'])->paginate(25));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'subject_id'  => 'required|exists:subjects,id',
        ]);

        $mapping = EmployeeSubject::create($validated);
        return response()->json(['message' => 'Employee-Subject mapping created', 'data' => $mapping], 201);
    }

    public function destroy($id)
    {
        EmployeeSubject::findOrFail($id)->delete();
        return response()->json(['message' => 'Mapping deleted']);
    }
}
