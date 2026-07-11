<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_session_id',
        'user_id',
        'joined_at',
        'left_at',
        'duration',
        'is_active'
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function session()
    {
        return $this->belongsTo(VideoSession::class, 'video_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}