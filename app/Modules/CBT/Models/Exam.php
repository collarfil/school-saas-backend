<?php

namespace App\Modules\Cbt\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Cross-Module Imports
use App\Modules\Core\Models\School;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\Grade;
use App\Modules\HR\Models\Employee;

class Exam extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'exams';

    protected $fillable = [
        'school_id',
        'exam_type_id',
        'subject_id',
        'employee_id',
        'school_session_id',
        'title',
        'instruction',
        'attachment',
        'available_from',
        'due_date',
        'duration_minutes',
        'max_score',
        'pass_mark',
        'randomize_questions',
        'randomize_options',
        'show_result_immediately',
        'allow_late_submission',
        'status',
    ];

    protected $casts = [
        'available_from' => 'datetime',
        'due_date' => 'datetime',
        'randomize_questions' => 'boolean',
        'randomize_options' => 'boolean',
        'show_result_immediately' => 'boolean',
        'allow_late_submission' => 'boolean',
        'max_score' => 'decimal:2',
        'pass_mark' => 'decimal:2',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function schoolSession()
    {
        return $this->belongsTo(SchoolSession::class, 'school_session_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }

    public function grades(): BelongsToMany
    {
        return $this->belongsToMany(Grade::class, 'exam_grade')->withTimestamps();
    }
}