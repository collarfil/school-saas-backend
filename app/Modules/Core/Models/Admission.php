<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Academics\Models\Grade;
// Update namespaces to match your module structure
use App\Modules\Academics\Models\SchoolSession; 

class Admission extends Model
{
    use HasFactory;

    protected $table = 'admission';

    // Status Constants for Lifecycle Management
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_INTERVIEW_SCHEDULED = 'interview_scheduled';
    public const STATUS_ADMITTED = 'admitted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ENROLLED = 'enrolled';

    protected $fillable = [
        'school_id',
        'school_session_id',
        'term',
        'application_number',
        'grade_id',
        'prev_grade',
        'prev_school',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'phone',
        'email',
        'address',
        'guardian_name',
        'guardian_relationship',
        'guardian_phone',
        'guardian_email',
        'status',
        'interview_date',
        'interview_venue',
        'interview_notes',
        'interview_notified_at',
        'decision_notified_at',
        'rejection_reason',
        'admission_list_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'interview_date' => 'datetime',
        'interview_notified_at' => 'datetime',
        'decision_notified_at' => 'datetime',
    ];

    // Inside App\Modules\Core\Models\Admission.php

protected static function boot()
{
    parent::boot();

    static::creating(function ($model) {
        if (empty($model->application_number)) {
            // Generates format: APP-2026-8X92K
            $model->application_number = 'APP-' . date('Y') . '-' . strtoupper(Str::random(5));
        }
    });
}
// Inside App\Modules\Core\Models\Admission.php

public function isAdmitted(): bool
{
    return $this->status === self::STATUS_ADMITTED;
}

public function markAsEnrolled(): void
{
    $this->update(['status' => self::STATUS_ENROLLED]);
}
    /**
     * Accessor for full applicant name
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    /* --- Relationships --- */

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

    public function admissionList()
    {
        return $this->belongsTo(AdmissionList::class, 'admission_list_id');
    }

    /* --- Query Scopes --- */

    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}