<?php

namespace App\Modules\Onlinelearning\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;


class Meeting extends Model{
    protected $fillable =[
     'school_id',
            'live_class_id',
            'provider',
            'meeting_id',
            'meeting_password',
            'meeting_url',
            'started_at',
            'ended_at',
            'duration',
            'total_participants',
            'status',
            'recording_available',
    ];
    protected $cast =[
        'recording_available' => 'boolean',
        
    ];

    public function school()
    {
        return $this -> belongsTo(School::class);
    }

    public function liveclass(){
        return $this->belongsTo(LiveClass::class);
    }
}