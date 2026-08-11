<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute; // <--- ADDED THIS IMPORT
use App\Modules\Academics\Models\Grade;
use App\Modules\Core\Models\School;
use App\Modules\HR\Models\Parents;
use App\Modules\Academics\Models\Result;
use App\Modules\Finance\Models\FeePayment;
use App\Modules\Core\Models\User;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'grade_id',
        'email',
        'admission_number',
        'parents_id',
        'gender',
        'is_active',
        'school_id',
        'phone'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Append parent_name to serialized model output
    protected $appends = ['parent_name', 'grade_name'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function parent()
    {
        return $this->belongsTo(Parents::class, 'parents_id');
    }

    public function results()
    {
        return $this->hasMany(Result::class);
    }

    public function feePayments()
    {
        return $this->hasMany(FeePayment::class);
    }

    /**
     * Accessor for parent_name
     */
    protected function parentName(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->relationLoaded('parent') && $this->parent) {
                    return $this->parent->name;
                }
                
                // Fallback attempt if relation not eager-loaded
                return $this->parent?->name ?? 'No Parent';
            }
        );
    }

    /**
     * Accessor for grade_name
     */
    protected function gradeName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->grade?->name ?? 'No Grade'
        );
    }
}