<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'term',
        'is_current',
        'school_id'
    ];

    protected $casts = [
        'is_current' => 'boolean',
    ];

    // Relationship to School
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    // Relationship to Fees
    public function fees()
    {
        return $this->hasMany(Fee::class, 'school_session_id');
    }

    // Relationship to Students (if needed)
    public function students()
    {
        return $this->hasMany(Student::class, 'session_id');
    }

    // Relationship to Results (if needed)
    public function results()
    {
        return $this->hasMany(Result::class, 'session_id');
    }

    // Scope for current session of a school
    public function scopeCurrent($query, $schoolId = null)
    {
        return $query->where('is_current', true)
                    ->when($schoolId, function($q) use ($schoolId) {
                        return $q->where('school_id', $schoolId);
                    });
    }

    // Scope for specific school
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }
}