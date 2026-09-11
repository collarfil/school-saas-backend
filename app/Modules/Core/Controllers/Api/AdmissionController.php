<?php

namespace App\Modules\Core\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Admission;
use App\Modules\Core\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdmissionController extends Controller
{
    private function resolveSchoolId(Request $request): ?int
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'isSuperAdmin') && !$user->isSuperAdmin()) {
            return $user->school_id;
        }
        return $request->query('school_id') ?? $request->input('school_id') ?? $user?->school_id;
    }

    /**
     * Generate a unique application number with zero collision risk.
     */
    private function generateUniqueApplicationNumber(): string
    {
        do {
            $number = 'ADM-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (Admission::where('application_number', $number)->exists());

        return $number;
    }

    public function index(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);

        if (!$schoolId) {
            return response()->json([
                'status' => 'error',
                'message' => 'School context identifier is missing.'
            ], 422);
        }

        $query = Admission::where('school_id', $schoolId)
            ->with(['grade', 'school', 'schoolSession', 'admissionList']);

        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->input('grade_id'));
        }

        if ($request->filled('school_session_id')) {
            $query->where('school_session_id', $request->input('school_session_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->input('gender'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('application_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('guardian_phone', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest()->paginate(50)
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);

        $validator = Validator::make(array_merge($request->all(), ['school_id' => $schoolId]), [
            'school_id' => 'required|exists:schools,id',
            'school_session_id' => 'nullable|exists:school_sessions,id',
            'term' => 'nullable|string|max:50',
            'grade_id' => 'required|exists:grades,id',
            'prev_grade' => 'nullable|string|max:255',
            'prev_school' => 'nullable|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|string|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'guardian_name' => 'nullable|string|max:255',
            'guardian_relationship' => 'nullable|string|max:100',
            'guardian_phone' => 'nullable|string|max:20',
            'guardian_email' => 'nullable|email|max:255',
            'status' => 'nullable|string|in:pending,under_review,interview_scheduled,admitted,rejected,enrolled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $admission = Admission::create(array_merge(
            $validator->validated(),
            [
                'application_number' => $this->generateUniqueApplicationNumber(),
                'status' => $request->input('status', Admission::STATUS_PENDING),
            ]
        ));

        return response()->json([
            'status' => 'success',
            'message' => 'Admission application created successfully',
            'data' => $admission->load(['grade', 'school', 'schoolSession'])
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $schoolId = $this->resolveSchoolId($request);

        $admission = Admission::where('school_id', $schoolId)
            ->with(['grade', 'school', 'schoolSession', 'admissionList'])
            ->find($id);

        if (!$admission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admission record not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $admission
        ]);
    }

    public function update(Request $request, $id)
    {
        $schoolId = $this->resolveSchoolId($request);

        $admission = Admission::where('school_id', $schoolId)->find($id);

        if (!$admission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admission record not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'school_session_id' => 'sometimes|nullable|exists:school_sessions,id',
            'term' => 'sometimes|nullable|string|max:50',
            'grade_id' => 'sometimes|required|exists:grades,id',
            'prev_grade' => 'sometimes|nullable|string|max:255',
            'prev_school' => 'sometimes|nullable|string|max:255',
            'first_name' => 'sometimes|required|string|max:255',
            'middle_name' => 'sometimes|nullable|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'gender' => 'sometimes|required|string|in:male,female,other',
            'date_of_birth' => 'sometimes|nullable|date',
            'phone' => 'sometimes|required|string|max:20',
            'email' => 'sometimes|nullable|email|max:255',
            'address' => 'sometimes|nullable|string|max:500',
            'guardian_name' => 'sometimes|nullable|string|max:255',
            'guardian_relationship' => 'sometimes|nullable|string|max:100',
            'guardian_phone' => 'sometimes|nullable|string|max:20',
            'guardian_email' => 'sometimes|nullable|email|max:255',
            'status' => 'sometimes|required|string|in:pending,under_review,interview_scheduled,admitted,rejected,enrolled',
            'interview_date' => 'sometimes|nullable|date',
            'interview_venue' => 'sometimes|nullable|string|max:255',
            'interview_notes' => 'sometimes|nullable|string',
            'rejection_reason' => 'sometimes|nullable|string',
            'admission_list_id' => 'sometimes|nullable|exists:admission_lists,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $admission->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Admission record updated successfully',
            'data' => $admission->fresh(['grade', 'school', 'schoolSession', 'admissionList'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $schoolId = $this->resolveSchoolId($request);

        $admission = Admission::where('school_id', $schoolId)->find($id);

        if (!$admission) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admission record not found.'
            ], 404);
        }

        $admission->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Admission record deleted successfully'
        ]);
    }

    // ======================== PUBLIC ADMISSION PORTAL ENDPOINTS ========================

   public function publicApply(Request $request)
{
    $validator = Validator::make($request->all(), [
        'school_uuid'           => 'required|exists:schools,uuid',
        'grade_id'              => 'required|exists:grades,id',
        'school_session_id'     => 'required|exists:school_sessions,id',
        'term'                  => 'nullable|string|max:50', // FIXED: Added term validation
        'prev_grade'            => 'nullable|string|max:255',
        'prev_school'           => 'nullable|string|max:255',
        'first_name'            => 'required|string|max:255',
        'middle_name'           => 'nullable|string|max:255',
        'last_name'             => 'required|string|max:255',
        'gender'                => 'required|string|in:male,female,other',
        'date_of_birth'         => 'nullable|date',
        'phone'                 => 'required|string|max:20',
        'email'                 => 'nullable|email|max:255',
        'address'               => 'nullable|string|max:500',
        'guardian_name'         => 'required|string|max:255',
        'guardian_relationship' => 'required|string|max:100',
        'guardian_phone'        => 'required|string|max:20',
        'guardian_email'        => 'nullable|email|max:255',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    $school = School::where('uuid', $request->school_uuid)
        ->where('is_unlocked', true)
        ->first();

    if (!$school) {
        return response()->json([
            'status' => 'error',
            'message' => 'School not found or portal is currently locked.'
        ], 404);
    }

    $session = DB::table('school_sessions')
        ->where('id', $request->school_session_id)
        ->where('school_id', $school->id)
        ->first();

    if (!$session) {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid session selected for this school.'
        ], 422);
    }

    $admission = Admission::create([
        'school_id'             => $school->id,
        'school_session_id'     => $session->id,
        'term'                  => $request->term ?? $session->term ?? '', // FIXED: Uses submitted term or defaults to session term
        'application_number'    => $this->generateUniqueApplicationNumber(),
        'grade_id'              => $request->grade_id,
        'prev_grade'            => $request->prev_grade ?? '',
        'prev_school'           => $request->prev_school,
        'first_name'            => $request->first_name,
        'middle_name'           => $request->middle_name,
        'last_name'             => $request->last_name,
        'gender'                => $request->gender,
        'date_of_birth'         => $request->date_of_birth,
        'phone'                 => $request->phone,
        'email'                 => $request->email,
        'address'               => $request->address ?? '',
        'guardian_name'         => $request->guardian_name,
        'guardian_relationship' => $request->guardian_relationship,
        'guardian_phone'        => $request->guardian_phone,
        'guardian_email'        => $request->guardian_email,
        'status'                => Admission::STATUS_PENDING,
    ]);

    return response()->json([
        'status' => 'success',
        'message' => 'Application submitted successfully.',
        'data' => [
            'application_number' => $admission->application_number,
            'applicant_name'     => trim("{$admission->first_name} {$admission->last_name}"),
            'school_name'        => $school->name,
            'status'             => $admission->status,
        ]
    ], 201);
}
    public function publicCheckStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'application_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application number is required',
                'errors' => $validator->errors()
            ], 422);
        }

        $admission = Admission::where('application_number', $request->application_number)
            ->with(['school:id,name,logo', 'grade:id,name'])
            ->first();

        if (!$admission) {
            return response()->json([
                'status' => 'error',
                'message' => 'No application found with the provided application number.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'application_number' => $admission->application_number,
                'applicant_name'     => trim("{$admission->first_name} {$admission->last_name}"),
                'school_name'        => $admission->school->name ?? null,
                'school_logo'        => $admission->school->logo ?? null,
                'applied_grade'      => $admission->grade->name ?? null,
                'status'             => $admission->status,
                'interview_date'     => $admission->interview_date,
                'interview_venue'    => $admission->interview_venue,
                'interview_notes'    => $admission->interview_notes,
                'rejection_reason'   => $admission->status === Admission::STATUS_REJECTED ? $admission->rejection_reason : null,
                'applied_at'         => $admission->created_at->toIso8601String(),
            ]
        ]);
    }
}