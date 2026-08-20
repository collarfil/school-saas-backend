<?php

namespace App\Modules\Cbt\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ExamGrade extends Pivot
{
    protected $table = 'exam_grade';
    
    protected $fillable = [
        'exam_id',
        'grade_id',
    ];
}