<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Academics\Models\Grade;
use App\Modules\Academics\Models\SchoolSession;

class AdmissionList extends Model
{
    use HasFactory;

    protected $table = 'admission_lists';

    protected $fillable = [
        'school_id',
        'school_session_id',
        'term',
        'grade_id',
        'title',
        'batch_number',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function schoolSession()
    {
        return $this->belongsTo(SchoolSession::class, 'school_session_id');
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function applicants()
    {
        return $this->hasMany(Admission::class, 'admission_list_id');
    }
}