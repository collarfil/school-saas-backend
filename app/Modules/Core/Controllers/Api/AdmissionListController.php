<?php

namespace App\Modules\Core\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Admission;
use App\Modules\Core\Models\AdmissionList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdmissionListController extends Controller
{
    private function resolveSchoolId(Request $request): ?int
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'isSuperAdmin') && !$user->isSuperAdmin()) {
            return $user->school_id;
        }
        return $request->query('school_id') ?? $request->input('school_id') ?? $user?->school_id;
    }

    public function index(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);

        if (!$schoolId) {
            return response()->json(['status' => 'error', 'message' => 'School context identifier is missing.'], 422);
        }

        $query = AdmissionList::where('school_id', $schoolId)
            ->with(['grade', 'schoolSession'])
            ->withCount('applicants');

        if ($request->filled('school_session_id')) {
            $query->where('school_session_id', $request->school_session_id);
        }

        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->filled('is_published')) {
            $query->where('is_published', filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('batch_number', 'like', "%{$search}%");
            });
        }

        $lists = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $lists
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);

        $validator = Validator::make(array_merge($request->all(), ['school_id' => $schoolId]), [
            'school_id'         => 'required|exists:schools,id',
            'school_session_id' => 'required|exists:school_sessions,id',
            'term'              => 'nullable|string|max:50',
            'grade_id'          => 'nullable|exists:grades,id',
            'title'             => 'required|string|max:255',
            'batch_number'      => 'required|string|max:50',
            'is_published'      => 'nullable|boolean',
            'applicant_ids'     => 'nullable|array',
            'applicant_ids.*'   => 'exists:admission,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $admissionList = DB::transaction(function () use ($request, $schoolId) {
            $isPublished = $request->boolean('is_published', false);

            $list = AdmissionList::create([
                'school_id'         => $schoolId,
                'school_session_id' => $request->school_session_id,
                'term'              => $request->term,
                'grade_id'          => $request->grade_id,
                'title'             => $request->title,
                'batch_number'      => $request->batch_number,
                'is_published'      => $isPublished,
                'published_at'      => $isPublished ? now() : null,
            ]);

            if ($request->filled('applicant_ids')) {
                Admission::where('school_id', $schoolId)
                    ->whereIn('id', $request->applicant_ids)
                    ->update([
                        'admission_list_id' => $list->id,
                        'status' => Admission::STATUS_ADMITTED
                    ]);
            }

            return $list;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Admission list created successfully',
            'data' => $admissionList->load(['grade', 'schoolSession', 'applicants'])
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $schoolId = $this->resolveSchoolId($request);

        $list = AdmissionList::where('school_id', $schoolId)
            ->with(['grade', 'schoolSession', 'applicants.grade'])
            ->find($id);

        if (!$list) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admission list not found.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $list
        ]);
    }

    public function update(Request $request, $id)
    {
        $schoolId = $this->resolveSchoolId($request);

        $list = AdmissionList::where('school_id', $schoolId)->find($id);

        if (!$list) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admission list not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'school_session_id' => 'sometimes|required|exists:school_sessions,id',
            'term'              => 'sometimes|nullable|string|max:50',
            'grade_id'          => 'sometimes|nullable|exists:grades,id',
            'title'             => 'sometimes|required|string|max:255',
            'batch_number'      => 'sometimes|required|string|max:50',
            'is_published'      => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        if ($request->has('is_published')) {
            $data['published_at'] = $request->is_published ? ($list->published_at ?? now()) : null;
        }

        $list->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Admission list updated successfully',
            'data' => $list->fresh(['grade', 'schoolSession', 'applicants'])
        ]);
    }

    public function publish(Request $request, $id)
    {
        $schoolId = $this->resolveSchoolId($request);

        $list = AdmissionList::where('school_id', $schoolId)->find($id);

        if (!$list) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admission list not found.'
            ], 404);
        }

        $list->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Admission list published successfully',
            'data' => $list
        ]);
    }

    public function syncApplicants(Request $request, $id)
    {
        $schoolId = $this->resolveSchoolId($request);

        $validator = Validator::make($request->all(), [
            'applicant_ids'   => 'required|array',
            'applicant_ids.*' => 'exists:admission,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $list = AdmissionList::where('school_id', $schoolId)->find($id);

        if (!$list) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admission list not found.'
            ], 404);
        }

        Admission::where('school_id', $schoolId)
            ->whereIn('id', $request->applicant_ids)
            ->update([
                'admission_list_id' => $list->id,
                'status' => Admission::STATUS_ADMITTED
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Applicants added to admission list successfully',
            'data' => $list->load('applicants')
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $schoolId = $this->resolveSchoolId($request);

        $list = AdmissionList::where('school_id', $schoolId)->find($id);

        if (!$list) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admission list not found.'
            ], 404);
        }

        DB::transaction(function () use ($list) {
            Admission::where('admission_list_id', $list->id)->update([
                'admission_list_id' => null
            ]);

            $list->delete();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Admission list deleted successfully'
        ]);
    }
}