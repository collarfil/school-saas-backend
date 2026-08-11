<?php

namespace App\Modules\Academics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Students\Models\Student; // ✅ Imported correct Student namespace

class Result extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id', 
        'grade_id', 
        'subject_id', 
        'school_session_id', 
        'term', 
        'score', 
        'score2', 
        'total', 
        'grade', 
        'is_locked', 
        'school_id'
    ];

    protected $casts = [
        'is_locked' => 'boolean'
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function student()
    { 
        return $this->belongsTo(Student::class); 
    }
    
    public function subject()
    { 
        return $this->belongsTo(Subject::class); 
    }
    
    public function grade()
    { 
        return $this->belongsTo(Grade::class); 
    }
    
    public function schoolSession()
    { 
        return $this->belongsTo(SchoolSession::class, 'school_session_id'); 
    }
}