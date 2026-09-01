<?php

namespace App\Modules\Finance\Models;

use App\Modules\Academics\Models\Grade;
use App\Modules\Core\Models\School;
use App\Modules\Academics\Models\SchoolSession;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'grade_id',
        'school_session_id',
        'term',
        'amount',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function grade(): BelongsTo 
    { 
        return $this->belongsTo(Grade::class); 
    }

    public function schoolSession(): BelongsTo 
    { 
        return $this->belongsTo(SchoolSession::class, 'school_session_id'); 
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function feePayments(): HasMany
    {
        return $this->hasMany(FeePayment::class);
    }
}