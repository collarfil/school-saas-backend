<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'teacher_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'meeting_id',
        'meeting_password',
        'status', // scheduled, active, ended
        'recording_url',
        'school_id'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function class()
    {
        return $this->belongsTo(Grade::class, 'class_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function participants()
    {
        return $this->hasMany(VideoParticipant::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}