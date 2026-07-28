<?php

namespace App\Modules\Models;


use Illuminate\Database\Eloquent\Model;

class Admission extends Model{
    protected $fillable =[
    'school_id',
            'grade_id',
            'prev_grade',
            'name',
            'gender',
            'phone',
            'address',
    ];
    public function school(){
        return $this->belongsTo(School::class);
    }
    public function grade(){
        return $this->belongsTo(Grade::class);
        
    }
}