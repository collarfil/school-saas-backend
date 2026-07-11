<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['grade_id', 'term', 'amount','school_session_id','description','school_id'];

    public function grade() { return $this->belongsTo(Grade::class); }
    public function schoolsession() { 
        return $this->belongsTo(SchoolSession::class, 'school_session_id'); 
    }
    public function school(){
        return $this->belongsTo(School::class);
    }
}

