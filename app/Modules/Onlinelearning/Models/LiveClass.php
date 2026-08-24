<?php
namespace App\Modules\Onlinelearning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Core\Models\School;
use App\Modules\HR\Models\Employee;
use App\Modules\Academics\Models\Grade;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\SchoolSession;
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
    public function schoolSession()
    {
        return $this->belongsTo(SchoolSession::class, 'school_session_id'); 
        // Note: Change 'school_session_id' if your foreign key has a different column name
    }
}