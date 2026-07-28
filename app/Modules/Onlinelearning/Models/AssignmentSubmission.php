<?php

namespace App\Modules\Onlinelearning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;


class AssignmentSubmission extends Model{
    protected $fillable =[
        'assignment_id',
        'student_id',
        'submission_text',
        'attachment',
        'submitted_at',
        'score',
        'remark',
        'graded_by',
        'graded_at',
        'status'
    ];
    protected $casts = [
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function grader()
    {
        return $this->belongsTo(Employee::class, 'graded_by');
    }
}