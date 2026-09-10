<?php

namespace App\Modules\Academics\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\Result;
use App\Modules\HR\Models\Student; // ✅ Fixed namespace
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ResultController extends Controller
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

        $query = Result::where('school_id', $request->school_id)
            ->with(['student', 'grade', 'subject', 'schoolSession', 'school']);

        if ($request->filled('grade_id')) $query->where('grade_id', $request->grade_id);
        if ($request->filled('subject_id')) $query->where('subject_id', $request->subject_id);
        if ($request->filled('school_session_id')) $query->where('school_session_id', $request->school_session_id);
        if ($request->filled('term')) $query->where('term', $request->term);
        if ($request->filled('student_id')) $query->where('student_id', $request->student_id);

        $results = $query->paginate(50);

        return response()->json([
            'status' => 'success',
            'data' => $results
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'school_session_id' => 'required|exists:school_sessions,id',
            'term' => 'required|string',
            'school_id' => 'required|exists:schools,id',
            'results' => 'required|array|min:1',
            'results.*.student_id' => 'required|exists:students,id',
            'results.*.score' => 'required|numeric|min:0|max:40',
            'results.*.score2' => 'required|numeric|min:0|max:60',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify all students belong to the same school
        $studentIds = collect($request->results)->pluck('student_id')->unique();
        $students = Student::whereIn('id', $studentIds)
            ->where('school_id', $request->school_id)
            ->count();
            
        if ($students !== count($studentIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'One or more students do not belong to this school.'
            ], 422);
        }

        $resultsCreated = 0;
        $resultsUpdated = 0;

        DB::transaction(function () use ($request, &$resultsCreated, &$resultsUpdated) {
            foreach ($request->results as $resultData) {
                $total = $resultData['score'] + $resultData['score2'];
                $grade = $this->calculateGrade($total);

                $result = Result::updateOrCreate(
                    [
                        'student_id' => $resultData['student_id'],
                        'grade_id' => $request->grade_id,
                        'subject_id' => $request->subject_id,
                        'school_session_id' => $request->school_session_id,
                        'term' => $request->term,
                        'school_id' => $request->school_id,
                    ],
                    [
                        'score' => $resultData['score'],
                        'score2' => $resultData['score2'],
                        'total' => $total,
                        'grade' => $grade,
                    ]
                );

                $result->wasRecentlyCreated ? $resultsCreated++ : $resultsUpdated++;
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Results processed successfully',
            'data' => [
                'created' => $resultsCreated,
                'updated' => $resultsUpdated,
                'total' => $resultsCreated + $resultsUpdated
            ]
        ]);
    }

    private function calculateGrade($total)
    {
        return match (true) {
            $total >= 70 => 'A',
            $total >= 60 => 'B',
            $total >= 50 => 'C',
            $total >= 45 => 'D',
            $total >= 40 => 'E',
            default => 'F',
        };
    }

    public function show(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:results,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = Result::where('school_id', $request->school_id)
            ->with(['student', 'grade', 'subject', 'schoolSession', 'school'])
            ->find($id);

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'Result not found or does not belong to this school.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $result
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:results,id',
            'score' => 'sometimes|required|numeric|min:0|max:40',
            'score2' => 'sometimes|required|numeric|min:0|max:60',
            'grade' => 'sometimes|required|string',
            'is_locked' => 'nullable|boolean',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = Result::where('school_id', $request->school_id)->find($id);

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'Result not found'
            ], 404);
        }

        if ($result->status === 'published') {
            return response()->json([
                'status' => 'error',
                'message' => 'Result already published and locked'
            ], 403);
        }

        if ($request->has('score') || $request->has('score2')) {
            $score = $request->has('score') ? $request->score : $result->score;
            $score2 = $request->has('score2') ? $request->score2 : $result->score2;
            $total = $score + $score2;
            
            $result->score = $score;
            $result->score2 = $score2;
            $result->total = $total;
            $result->grade = $this->calculateGrade($total);
        }

        if ($request->has('is_locked')) {
            $result->is_locked = $request->is_locked;
        }

        if ($request->has('grade') && !$request->has('score') && !$request->has('score2')) {
            $result->grade = $request->grade;
        }

        $result->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Result updated successfully',
            'data' => $result->fresh(['student', 'grade', 'subject', 'schoolSession', 'school'])
        ]);
    }

    public function publishResults(Request $request)
    {
        Result::where([
            'school_id' => $request->school_id,
            'school_session_id' => $request->school_session_id,
            'term' => $request->term
        ])->update(['status' => 'published']);

        return response()->json([
            'status' => 'success',
            'message' => 'Results published and locked'
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:results,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = Result::where('school_id', $request->school_id)->find($id);

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'Result not found or does not belong to this school.'
            ], 404);
        }

        if ($result->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete locked result. Unlock it first.'
            ], 422);
        }

        $result->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Result deleted successfully'
        ]);
    }

    public function lock(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:results,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = Result::where('school_id', $request->school_id)->find($id);

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'Result not found or does not belong to this school.'
            ], 404);
        }

        $result->update(['is_locked' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Result locked successfully',
            'data' => $result
        ]);
    }

    public function unlock(Request $request, $id)
    {
        $validator = Validator::make([
            'id' => $id,
            'school_id' => $request->school_id
        ], [
            'id' => 'required|exists:results,id',
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = Result::where('school_id', $request->school_id)->find($id);

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'Result not found or does not belong to this school.'
            ], 404);
        }

        $result->update(['is_locked' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Result unlocked successfully',
            'data' => $result
        ]);
    }

     // Inside App\Modules\Academics\Controllers\Api\ResultController.php

public function studentReport(Request $request)
{
    $validator = Validator::make($request->all(), [
        'school_id'         => 'required|exists:schools,id',
        'student_id'        => 'required|exists:students,id',
        'school_session_id' => 'required|exists:school_sessions,id',
        'term'              => 'required|string'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'errors' => $validator->errors()
        ], 422);
    }

    $studentId = $request->student_id;
    $schoolId  = $request->school_id;
    $sessionId = $request->school_session_id;
    $term      = $request->term;

    // 1. Fetch Student Details
    $student = Student::where('id', $studentId)
        ->where('school_id', $schoolId)
        ->firstOrFail();

    // 2. Fetch Results
    $results = Result::where([
        'school_id'         => $schoolId,
        'student_id'        => $studentId,
        'school_session_id' => $sessionId,
        'term'              => $term,
    ])
    ->with(['subject', 'school', 'schoolSession'])
    ->get();

    // 3. Format Subjects Array for Template
    $subjects = $results->map(function ($res) {
        $caScore   = (float) ($res->score ?? 0);
        $examScore = (float) ($res->score2 ?? 0);
        $total     = (float) ($res->total ?? ($caScore + $examScore));

        return [
            'id'         => $res->subject_id,
            'name'       => $res->subject?->name ?? 'Unknown Subject',
            'ca_score'   => $caScore,
            'exam_score' => $examScore,
            'total'      => $total,
            'average'    => $total,
            'grade'      => $res->grade ?? null
        ];
    });

    // 4. Calculate Summary Metrics
    $studentTotal = $subjects->sum('total');
    $average      = $subjects->count() > 0 ? round($studentTotal / $subjects->count(), 2) : 0;

    // 5. Calculate Class Position
    $classTotals = Result::where([
        'school_id'         => $schoolId,
        'school_session_id' => $sessionId,
        'term'              => $term,
    ])
    ->get()
    ->groupBy('student_id')
    ->map(fn($items) => $items->sum('total'))
    ->sortDesc()
    ->values();

    $studentRank = $classTotals->search($studentTotal);
    $position = '-';
    if ($studentRank !== false) {
        $pos = $studentRank + 1;
        $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
        $position = (($pos % 100) >= 11 && ($pos % 100) <= 13) ? $pos . 'th' : $pos . $ends[$pos % 10];
    }

    // 6. Return Payload Matched to React Template
    return response()->json([
        'status' => 'success',
        'data'   => [
            'term_name'      => is_numeric($term) ? "Term {$term}" : ucfirst($term),
            'session_name'   => $results->first()?->schoolSession?->name ?? 'Current Session',
            'total_students' => $classTotals->count(),
            'subjects'       => $subjects,
            'overall' => [
                'total'    => $studentTotal,
                'average'  => $average,
                'position' => $position,
            ],
            'attendance' => [
                'times_opened'  => '-',
                'times_present' => '-',
                'height'        => '-',
                'weight'        => '-',
            ],
            'traits' => [
                'participation'  => 'A',
                'homework'       => 'A',
                'projects'       => 'B',
                'leadership'     => 'A',
                'politeness'     => 'A',
                'punctuality'    => 'A',
                'interaction'    => 'A',
                'responsibility' => 'A',
            ],
            'teacher_comment'      => 'A commendable performance throughout the term.',
            'head_teacher_comment' => 'Satisfactory effort.',
            'class_teacher'        => 'Class Teacher',
            'head_teacher'         => 'Principal',
            'signature_date'       => now()->format('Y-m-d'),
        ]
    ]);
}

    public function approveResults(Request $request)
    {
        Result::where([
            'school_id' => $request->school_id,
            'school_session_id' => $request->school_session_id,
            'term' => $request->term
        ])->update(['status' => 'approved']);

        return response()->json([
            'status' => 'success',
            'message' => 'Results approved successfully'
        ]);
    }

    public function pendingGrading(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => []
        ]);
    }

    public function myGrades(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => []
        ]);
    }
}