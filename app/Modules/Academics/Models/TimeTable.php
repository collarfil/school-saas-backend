<?php
namespace App\Modules\Academics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeTable extends Models{
   
    protected $fillable =[
        'school_id',
            'school_session_id',
            'grade_id',
            'subject_id',
            'day',
            'period',
    ];

    public function school(){
        return $this-> belongsTo(School::class);

    }
    public function schoolsession(){
        return $this->belongsTo(schoolsession::class);
    }
    public function grade(){
        return $this-> belongsTo(Grade::class);
    }
    public function subject(){
        return $this->belongsTo(Subject::class);
    }
}