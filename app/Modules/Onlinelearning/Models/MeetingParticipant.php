<?php

namespace App\Modules\Onlinelearning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;


class MeetingParticipant extends Model{
    protected $fillable =[
      'meeting_id',
            'user_id',
            'role',
            'joined_at',
            'left_at',
            'attendance_duration',
            'camera_enabled',
            'microphone_enabled',
            'hand_raised',
            'connection_quality',
            
    ];


    public function meeting(){
        return $this->belongsTo(Metting::class);
    }
    
}