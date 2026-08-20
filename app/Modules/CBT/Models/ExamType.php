<?php

namespace App\Modules\Cbt\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Core\Models\School;

class ExamType extends Model
{
    use HasFactory;

    protected $table = 'exam_types';

    protected $fillable = [
        'school_id',
        'school_session_id',
        'name',
        'slug',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolSession()
    {
        return $this->belongsTo(SchoolSession::class, 'school_session_id');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}