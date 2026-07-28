<?php

namespace App\Modules\Onlinelearning\Models;

use Illuminate\Database\Eloquent\Model;


class Assignment extends Model{
    protected $fillable =[
        'live_class_id',
        'school_id',
        'subject_id',
        'teacher_id',
        'title',
        'instruction',
        'attachment',
        'available_from',
        'due_date',
        'max_score',
        'allow_late_submission',
        'status',

    ];
    protected $casts = [
        'available_from' => 'datetime',
        'due_date' => 'datetime',
    ];

    public function liveClass()
    {
        return $this->belongsTo(LiveClass::class, 'live_class_id');
    }
    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }
    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
    public function teacher()
    {
        return $this->belongsTo(Employee::class, 'teacher_id');
    }
}