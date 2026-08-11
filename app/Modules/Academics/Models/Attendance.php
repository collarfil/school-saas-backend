<?php

namespace App\Modules\Academics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\HR\Models\Student;
use App\Modules\Core\Models\School;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'attendances';

    protected $fillable = [
        'grade_id',
        'student_id',
        'school_session_id',
        'attendance_date',
        'is_present',
        'school_id'
    ];

    protected $casts = [
        'attendance_date' => 'date:Y-m-d',
        'is_present'      => 'boolean',
    ];

    // --- Relationships ---
    public function school()
    {
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