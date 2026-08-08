<?php

namespace App\Modules\Core\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdmissionController extends Controller
{
    public function index(Request $request)
    {
        $admissions = Admission::all();
        return response()->json($admissions);
    
     if($request->has('admission_id')) {
            $admission = Admission::find($request->admission_id);
            return response()->json($admission);
        }

        return response()->json(['error' => 'Admission not found'], 404);
    }   
    public function show(Request $request, $id)
    {
        $admission = Admission::find($id);
        return response()->json($admission);
    }

    public function store(Request $request)
    {

    }

    public function update(Request $request, $id)
    {

    }

    public function destroy(Request $request, $id)
    {

    }
}

