<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use Illuminate\Http\Request;

class ClassSessionController extends Controller
{
    public function index()
    {
        return ClassSession::latest()->get();
    }

    public function show($id)
    {
        return ClassSession::findOrFail($id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'meeting_link' => 'required|url',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
        ]);

        return ClassSession::create($request->all());
    }

    public function update(Request $request, $id)
    {
        $session = ClassSession::findOrFail($id);
        $session->update($request->all());
        return $session;
    }

    public function destroy($id)
    {
        $session = ClassSession::findOrFail($id);
        $session->delete();
        return response()->noContent();
    }

    public function startSession($id)
    {
        $session = ClassSession::findOrFail($id);
        $session->is_live = true;
        $session->save();
        return $session;
    }

    public function endSession($id)
    {
        $session = ClassSession::findOrFail($id);
        $session->is_live = false;
        $session->save();
        return $session;
    }
}
