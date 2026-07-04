<?php

namespace App\Http\Controllers\Api;

use App\Services\UserCreationService;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;



class StudentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'school_id' => 'required|exists:schools,id',
                'is_active' => 'nullable|boolean',
                'grade_id' => 'nullable|exists:grades,id',
                'parents_id' => 'nullable|exists:parents,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = Student::where('school_id', $request->school_id)
                ->with(['grade', 'parent', 'school', 'results', 'feePayments']);

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Filter by grade
            if ($request->filled('grade_id')) {
                $query->where('grade_id', $request->grade_id);
            }

            // Filter by parent
            if ($request->filled('parents_id')) {
                $query->where('parents_id', $request->parents_id);
            }

            $students = $query->orderBy('name')->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $students
            ]);
        } catch (\Exception $e) {
            Log::error('StudentController index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch students',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    


        public function store(Request $request)
        {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'nullable|email',
                'grade_id' => 'nullable|exists:grades,id',
                'parent_id' => 'nullable|exists:parents,id',
                'phone' => 'required|string|max:20',
                'admission_number' => 'nullable|string|unique:students',
                'gender' => 'nullable|in:male,female,other,Male,Female,Other',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            try {
                DB::beginTransaction();

                $schoolId = auth()->user()->school_id;
                $temporaryPassword = $request->phone;
                
                // Generate unique username from name
                $username = $this->generateUsername($request->name, $schoolId);

                // Create user account with generated username as email
                $user = User::create([
                    'name' => $request->name,
                    'email' => $username . '@school.local', // Use generated username
                    'password' => Hash::make($temporaryPassword),
                    'role' => 'student',
                    'school_id' => $schoolId,
                    'phone' => $request->phone,
                    'is_active' => true,
                    'must_change_password' => true,
                ]);

                // Create student record
                $student = Student::create([
                    'user_id' => $user->id,
                    'school_id' => $schoolId,
                    'admission_number' => $request->admission_number ?? $this->generateAdmissionNumber(),
                    'grade_id' => $request->grade_id,
                    'parents_id' => $request->parent_id,
                    'gender' => $request->gender,
                    'name' => $request->name,
                    'email' => $request->email, // Store original email (can be null or shared)
                    'phone' => $request->phone,
                    'is_active' => true,
                ]);

                DB::commit();

                // Prepare credentials for display
                $credentials = [
                    'username' => $username,
                    'email' => $user->email,
                    'password' => $temporaryPassword,
                    'name' => $user->name,
                    'role' => 'student',
                    'admission_number' => $student->admission_number,
                    'note' => 'Use your username or email to login. Phone number is your temporary password.'
                ];

                return response()->json([
                    'message' => 'Student created successfully',
                    'data' => $student,
                    'user' => $user->makeHidden(['password']),
                    'credentials' => $credentials
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Student creation failed: ' . $e->getMessage());
                
                return response()->json([
                    'message' => 'Student creation failed: ' . $e->getMessage()
                ], 500);
            }
        }

        // Add this helper method to generate unique username
        private function generateUsername($name, $schoolId)
        {
            $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $name));
            $username = $baseUsername;
            $counter = 1;
            
            // Check if username already exists in users table (email column stores username@school.local)
            while (User::where('email', $username . '@school.local')->exists()) {
                $username = $baseUsername . $counter;
                $counter++;
            }
            
            return $username;
        }



    private function generateAdmissionNumber()
    {
        $year = date('Y');
        $lastStudent = Student::whereYear('created_at', $year)->latest()->first();
        $lastNumber = $lastStudent ? intval(substr($lastStudent->admission_number, -4)) : 0;
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        
        return 'ADM/' . $year . '/' . $newNumber;
    }

    public function show(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:students,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $student = Student::where('school_id', $request->school_id)
                ->with(['grade', 'parent', 'school', 'results', 'feePayments'])
                ->find($id);

            if (!$student) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Student not found or does not belong to this school.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $student
            ]);
        } catch (\Exception $e) {
            Log::error('StudentController show error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
                'id' => 'required|exists:students,id',
                'grade_id' => 'sometimes|exists:grades,id',
                'parents_id' => 'nullable|exists:parents,id',
                'name' => 'sometimes|string|max:255',
                'admission_number' => 'sometimes|string',
                'email' => 'nullable|email',
                'gender' => 'nullable|string|in:Male,Female,Other',
                'is_active' => 'nullable|boolean',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $student = Student::where('school_id', $request->school_id)->find($id);

            if (!$student) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Student not found or does not belong to this school.'
                ], 404);
            }

            // Check if admission number is unique within the school (excluding current student)
            if ($request->has('admission_number')) {
                $existingStudent = Student::where('school_id', $request->school_id)
                    ->where('admission_number', $request->admission_number)
                    ->where('id', '!=', $id)
                    ->first();
                    
                if ($existingStudent) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'A student with this admission number already exists in this school.'
                    ], 422);
                }
            }

            // Check if email is unique within the school (excluding current student)
            if ($request->has('email') && !empty($request->email)) {
                $existingStudentByEmail = Student::where('school_id', $request->school_id)
                    ->where('email', $request->email)
                    ->where('id', '!=', $id)
                    ->first();
                    
                if ($existingStudentByEmail) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'A student with this email already exists in this school.'
                    ], 422);
                }
            }

            $student->update($validator->validated());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Student updated successfully', 
                'data' => $student->fresh(['grade', 'parent', 'school'])
            ]);
            
        } catch (\Exception $e) {
            Log::error('StudentController update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update student',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:students,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $student = Student::where('school_id', $request->school_id)->find($id);

            if (!$student) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Student not found or does not belong to this school.'
                ], 404);
            }

            // Check if student has results or fee payments before deleting
            $hasResults = $student->results()->count() > 0;
            $hasFeePayments = $student->feePayments()->count() > 0;
            
            if ($hasResults || $hasFeePayments) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete student because they have ' . 
                                 ($hasResults ? 'results' : '') . 
                                 ($hasResults && $hasFeePayments ? ' and ' : '') . 
                                 ($hasFeePayments ? 'fee payments' : '') . 
                                 '. Please archive instead.'
                ], 422);
            }

            $student->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Student deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('StudentController destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete student',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make([
                'id' => $id,
                'school_id' => $request->school_id
            ], [
                'id' => 'required|exists:students,id',
                'school_id' => 'required|exists:schools,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $student = Student::where('school_id', $request->school_id)->find($id);

            if (!$student) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Student not found or does not belong to this school.'
                ], 404);
            }

            $student->update(['is_active' => !$student->is_active]);

            return response()->json([
                'status' => 'success',
                'message' => 'Student ' . ($student->is_active ? 'activated' : 'deactivated') . ' successfully',
                'data' => $student
            ]);
        } catch (\Exception $e) {
            Log::error('StudentController toggleStatus error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle student status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}