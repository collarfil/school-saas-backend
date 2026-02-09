<?php

namespace App\Services;

use App\Models\User;
use App\Models\Employee;
use App\Models\Student;
use App\Models\Parents;
use Illuminate\Support\Facades\Hash;

class UserCreationService
{
    /**
     * Create a User for an Employee
     */
    public static function createEmployeeUser(Employee $employee)
    {
        return User::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'name' => $employee->name,
                'email' => $employee->email,
                'role' => 'employee',
                'school_id' => $employee->school_id,
                'employee_type' => $employee->role, // teaching/non_teaching
                'password' => Hash::make('defaultpassword'),
                'must_change_password' => true,
                'is_active' => true,
            ]
        );
    }

    /**
     * Create a User for a Student
     */
    public static function createStudentUser(Student $student)
    {
        return User::updateOrCreate(
            ['student_id' => $student->id],
            [
                'name' => $student->name,
                'email' => $student->email,
                'role' => 'student',
                'school_id' => $student->school_id,
                'password' => Hash::make('defaultpassword'),
                'must_change_password' => true,
                'is_active' => true,
            ]
        );
    }

    /**
     * Create a User for a Parent
     */
    public static function createParentUser(Parents $parent)
    {
        return User::updateOrCreate(
            ['parent_id' => $parent->id],
            [
                'name' => $parent->name,
                'email' => $parent->email,
                'role' => 'parent',
                'school_id' => $parent->school_id,
                'password' => Hash::make('defaultpassword'),
                'must_change_password' => true,
                'is_active' => true,
            ]
        );
    }
}
