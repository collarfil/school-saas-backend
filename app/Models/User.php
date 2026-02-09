<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'school_id', 'role', 'phone', 
        'is_active', 'must_change_password', 'employee_type', 'last_login_at'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
    ];

    // Role Checks
    public function isSuperAdmin()
    {
        return $this->role === 'super_admin';
    }

    public function isSchoolAdmin()
    {
        return $this->role === 'admin';
    }

    public function isEmployee()
    {
        return $this->role === 'employee';
    }

    public function isStudent()
    {
        return $this->role === 'student';
    }

    public function isParent()
    {
        return $this->role === 'parent';
    }

    // Employee Type Checks
    public function isTeachingStaff()
    {
        return $this->isEmployee() && $this->employee_type === 'teaching';
    }

    public function isNonTeachingStaff()
    {
        return $this->isEmployee() && $this->employee_type === 'non_teaching';
    }

    public function isAccountStaff()
    {
        return $this->isNonTeachingStaff() && $this->hasPermission('financials.access');
    }

    // Permission-based Access Control
    public function hasPermission($permission)
    {
        $permissions = $this->getPermissions();
        return in_array($permission, $permissions) || in_array('*', $permissions);
    }

    public function hasRole($role)
    {
        return $this->role === $role;
    }

    public function getPermissions()
    {
        $rolePermissions = [
            'super_admin' => ['*'],
            'admin' => [
                // School Management
                'school.manage',
                'school.settings',
                
                // Academic Management
                'academic.view',
                'academic.manage',
                'sessions.manage',
                'grades.manage',
                'subjects.manage',
                'sections.manage',
                'timetable.manage',
                'curriculum.manage',
                
                // Student Management
                'students.manage',
                'students.view',
                'students.enroll',
                'students.promote',
                
                // Employee Management
                'employees.manage',
                'employees.view',
                'employees.assign',
                
                // Parent Management
                'parents.manage',
                'parents.view',
                
                // Financial Management
                'financials.access',
                'fees.manage',
                'payments.manage',
                'invoices.manage',
                'reports.financial',
                
                // Results & Exams
                'results.manage',
                'results.view',
                'results.record',
                'exams.manage',
                'assessments.manage',
                
                // Attendance
                'attendance.manage',
                'attendance.view',
                'attendance.reports',
                
                // Subscription
                'subscription.manage',
                'subscription.view',
                'subscription.upgrade',
                
                // Dashboard & Profile
                'dashboard.access',
                'profile.manage',
                'profile.view',
                
                // Reports
                'reports.view',
                'reports.generate',
                'analytics.view',
                
                // Settings
                'settings.manage',
                'notifications.manage',
                'backup.manage'
            ],
            'employee' => [
                'dashboard.access',
                'profile.manage',
                'profile.view',
                'attendance.view',
                'timetable.view',
                'students.view',
                'results.record',
                'attendance.manage'
            ],
            'teaching' => [
                'results.record',
                'students.view',
                'attendance.manage',
                'subjects.manage',
                'assessments.manage',
                'timetable.view',
                'academic.view'
            ],
            'account' => [
                'financials.access',
                'fees.manage',
                'payments.manage',
                'reports.financial',
                'invoices.manage',
                'students.view'
            ],
            'student' => [
                'results.view',
                'profile.view',
                'payments.view',
                'attendance.view',
                'timetable.view',
                'academic.view'
            ],
            'parent' => [
                'student.results.view',
                'payments.view',
                'profile.view',
                'attendance.view',
                'timetable.view',
                'academic.view'
            ]
        ];

        $permissions = $rolePermissions[$this->role] ?? [];

        // Add employee type specific permissions
        if ($this->isEmployee() && $this->employee_type) {
            $typePermissions = $rolePermissions[$this->employee_type] ?? [];
            $permissions = array_merge($permissions, $typePermissions);
        }

        // Add common permissions for all authenticated users
        $commonPermissions = [
            'dashboard.access',
            'profile.manage',
            'profile.view'
        ];
        
        $permissions = array_merge($permissions, $commonPermissions);

        return array_unique($permissions);
    }

    // Relationships
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function hasActiveSubscription()
    {
        if ($this->isSuperAdmin()) return true;
        return $this->school && $this->school->hasActiveSubscription();
    }

    public function needsPasswordChange()
    {
        return (bool) $this->must_change_password;
    }

    // Check if user can access school features
    public function canAccessSchoolFeatures()
    {
        if ($this->isSuperAdmin()) return true;
        return $this->school && $this->school->is_unlocked;
    }

    // JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,
            'school_id' => $this->school_id,
            'permissions' => $this->getPermissions(),
            'can_access_features' => $this->canAccessSchoolFeatures()
        ];
    }

    // Update last login timestamp
    public function updateLastLogin()
    {
        $this->update(['last_login_at' => now()]);
    }

    // Scope for active users
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope for school users
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    // Scope by role
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    // Check if user can manage students
    public function canManageStudents()
    {
        return $this->hasPermission('students.manage') || $this->isSuperAdmin();
    }

    // Check if user can manage financials
    public function canManageFinancials()
    {
        return $this->hasPermission('financials.access') || $this->isSuperAdmin();
    }

    // Check if user can record results
    public function canRecordResults()
    {
        return $this->hasPermission('results.record') || $this->isSuperAdmin();
    }
}