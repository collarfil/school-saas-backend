<?php

namespace App\Modules\Academics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
// Import sibling/core models explicitly to ensure relationships resolve across modules
use App\Modules\Core\Models\School;

class TimeTable extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'time_tables';

    protected $fillable = [
        'school_id',
        'school_session_id',
        'grade_id',
        'subject_id',
        'day',
        'period',
    ];

    /**
     * Relationship to School (Core)
     */
    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * Relationship to SchoolSession (Academics)
     * Key Fix: Standardized method name 'schoolSession' mapped explicitly to 'school_session_id'
     */
    public function schoolSession()
    {
        return $this->belongsTo(SchoolSession::class, 'school_session_id');
    }

    /**
     * Relationship to Grade (Academics)
     */
    public function grade()
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    /**
     * Relationship to Subject (Academics)
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}