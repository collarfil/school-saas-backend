<?php

namespace App\Modules\Onlinelearning\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;


class Recording extends Model{
    protected $fillable =[
        'meeting_id',
        'school_id',
        'file_name',
        'file_url',
        'duration',
        'size',
        'provider',
        'visibility',
        'created_by',
    ];

    protected $casts = [
        'visibility' => 'boolean',
    ];

    public function meeting(){
        return $this->belongsTo(Meeting::class, 'meeting_id', 'id');
    }
    public function school(){
        return $this->belongsTo(School::class);
    }
}