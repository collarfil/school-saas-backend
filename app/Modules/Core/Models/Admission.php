<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
// Import external module model for Grade
use App\Modules\Academics\Models\Grade;

class Admission extends Model
{
    use HasFactory;

    // Explicitly defining table name
    protected $table = 'admission';

    protected $fillable = [
        'school_id',
        'grade_id',
        'prev_grade',
        'name',
        'gender',
        'phone',
        'address',
    ];

    /**
     * Relationship to School (Core)
     */
    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * Relationship to Grade (Academics Module)
     * Key Fix: Explicit class reference to Grade model across module boundary
     */
    public function grade()
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }
}