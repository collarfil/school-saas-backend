<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'grade_id',
        'student_id',
        'school_session_id',
        'attendance_date',
        'is_present',
        'school_id'
    ];

    protected $casts = [
        'attendance_date' => 'datetime',
        'is_present' => 'boolean',
    ];

    // --- Relationships ---
    public function school(){
        return $this->belongsTo(School::class);
    }
    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolSession()
    {
        return $this->belongsTo(SchoolSession::class);
    }
}
