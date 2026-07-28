<?php

namespace App\Modules\Onlinelearning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassAttendance extends Model{
    protected $fillable =[
    'meeting_id',    
    'student_id',
    'joined_at',
    'left_at',
    'duration',
    'attendance_status',

    ];
    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];
    public function student(){
        return $this->belongsTo(Student::class, 'student_id');
    }
    public function meeting(){
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }
}