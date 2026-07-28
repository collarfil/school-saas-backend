<?php

namespace App\Modules\Onlinelearning\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;


class PollResponse extends Model{
    protected $fillable = [
    'poll_id',
    'student_id',
    'selected_option',
    'answered_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
    ];

    public function poll(){
        return $this->belongsTo(Poll::class, 'poll_id');
    }
    public function student(){
        return $this->belongsTo(Student::class, 'student_id');
    }
}