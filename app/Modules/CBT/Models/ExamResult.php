<?php

namespace App\Modules\Cbt\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Cross-Module Imports
use App\Modules\Core\Models\School;
use App\Modules\HR\Models\Student;
use App\Modules\Core\Models\User; // Assuming User model lives inside Core

class ExamResult extends Model
{
    use HasFactory;

    protected $table = 'exam_results';

    protected $fillable = [
        'school_id',
        'exam_id',
        'student_id',
        'exam_session_id',
        'score_obtained',
        'percentage',
        'is_passed',
        'graded_by',
        'teacher_remarks',
    ];

    protected $casts = [
        'score_obtained' => 'decimal:2',
        'percentage' => 'decimal:2',
        'is_passed' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}