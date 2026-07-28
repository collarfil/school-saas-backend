<?php

namespace App\Modules\Onlinelearning\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;


class Poll extends Model{
    protected $fillable =[
    'meeting_id',
    'question',
    'options',
    'correct_answer',
    'created_by',
    ];

    public function meeting(){
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }
}