<?php

namespace App\Modules\Onlinelearning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;


class LiveChat extends Model{
    protected $fillable =[
    'meeting_id',
    'user_id',
    'message',
    'reply_to',
    'is_teacher_message',
    'created_at'

    ];
 
     protected $casts = [
        'is_teacher_message' => 'boolean',
        'created_at' => 'datetime'
    ];

    public function meeting(){
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }
    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(LiveChat::class, 'reply_to', 'id');
    }

    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(LiveChat::class, 'reply_to', 'id');
    }
}