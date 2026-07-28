<?php
namespace App\Modules\Onlinelearning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiveClass extends Model
{
    protected $fillable =[
    'school_id',
        'grade_id',
        'employee_id',
        'subject_id',
        'school_session_id',
        'title',
        'description',
        'meeting_provider',
        'meeting_url',
        'meeting_code',
        'schedule_date',
        'start_time',
        'end_time',
        'status',
        'is_recorded',
        'allow_chat',
        'allow_screen_share',
        'allow_student_microphone',
        'allow_student_camera'
    ];

    protected $cast =[
        'schedule_date' => 'datetime',
        'is_recorded' => 'boolean',
    ];
    public function school(){
        return $this->belongsTo(School::class);
    }
    

    public function grade(){
        return $this->belongsTo(Grade::class);
    }

    public function employee(){
        return $this->belongsTo(Employee::class);
    }
    public function subject(){
        return $this->belongsTo(Subject::class);
    
    }
    public function school_session(){
        return $this->belongsTo(SchoolSession::class);
    }
}