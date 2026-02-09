<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPricing extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_type', 'base_price', 'per_student_price', 
        'duration_days', 'description', 'is_active'
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'per_student_price' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    public static function getActivePricing()
    {
        $pricings = self::where('is_active', true)->get();
        
        $formatted = [];
        foreach ($pricings as $pricing) {
            $formatted[$pricing->plan_type] = [
                'base_price' => (float) $pricing->base_price,
                'per_student' => (float) $pricing->per_student_price,
                'duration_days' => $pricing->duration_days,
                'duration' => self::formatDuration($pricing->duration_days),
                'description' => $pricing->description,
                'updated_at' => $pricing->updated_at->toISOString()
            ];
        }

        // Ensure we have both plan types
        if (!isset($formatted['termly'])) {
            $formatted['termly'] = [
                'base_price' => 20000,
                'per_student' => 2000,
                'duration_days' => 120,
                'duration' => '4 months',
                'description' => 'Per term subscription',
                'updated_at' => now()->toISOString()
            ];
        }

        if (!isset($formatted['yearly'])) {
            $formatted['yearly'] = [
                'base_price' => 50000,
                'per_student' => 5000,
                'duration_days' => 365,
                'duration' => '1 year',
                'description' => 'Annual subscription (3 terms)',
                'updated_at' => now()->toISOString()
            ];
        }

        return $formatted;
    }

    private static function formatDuration($days)
    {
        if ($days >= 365) return '1 year';
        if ($days >= 120) return '4 months';
        if ($days >= 90) return '3 months';
        if ($days >= 60) return '2 months';
        if ($days >= 30) return '1 month';
        return "{$days} days";
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPlanType($query, $planType)
    {
        return $query->where('plan_type', $planType);
    }
}