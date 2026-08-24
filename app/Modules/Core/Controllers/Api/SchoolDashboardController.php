<?php

namespace App\Modules\Core\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Models
use App\Modules\HR\Models\Student;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Parents;
use App\Modules\Finance\Models\FeePayment;

class SchoolDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user || !$user->school_id) {
            return response()->json([
                'message' => 'User not linked to a school',
                'stats' => $this->emptyStats()
            ], 403);
        }

        $schoolId = $user->school_id;

        $stats = [
            'total_students'  => Student::where('school_id', $schoolId)->count(),
            'total_employees' => Employee::where('school_id', $schoolId)->count(),
            'total_parents'   => Parents::where('school_id', $schoolId)->count(),
            'fees_collected'  => FeePayment::where('school_id', $schoolId)
                ->where('status', 'paid')
                ->sum('amount_paid'),
        ];

        return response()->json([
            'message' => 'Dashboard loaded',
            'stats'   => $stats
        ]);
    }

    private function emptyStats(): array
    {
        return [
            'total_students'  => 0,
            'total_employees' => 0,
            'total_parents'   => 0,
            'fees_collected'  => 0,
        ];
    }
}
