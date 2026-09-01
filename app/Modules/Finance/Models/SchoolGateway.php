<?php
// app/Modules/Finance/Models/SchoolGateway.php

namespace App\Modules\Finance\Models;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'provider',
        'api_public_key',
        'api_secret_key',
        'webhook_secret',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the public key (automatically decrypts if encrypted)
     */
    public function getApiPublicKeyAttribute($value)
    {
        if (empty($value)) {
            return null;
        }
        
        // Check if the value looks like it's encrypted (starts with encrypted:)
        if (str_starts_with($value, 'encrypted:')) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
                // If decryption fails, return the value as is
                return $value;
            }
        }
        
        return $value;
    }

    /**
     * Set the public key (automatically encrypts)
     */
    public function setApiPublicKeyAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['api_public_key'] = null;
            return;
        }
        
        // Only encrypt if it's not already encrypted
        if (!str_starts_with($value, 'encrypted:')) {
            $this->attributes['api_public_key'] = 'encrypted:' . encrypt($value);
        } else {
            $this->attributes['api_public_key'] = $value;
        }
    }

    /**
     * Get the secret key (automatically decrypts if encrypted)
     */
    public function getApiSecretKeyAttribute($value)
    {
        if (empty($value)) {
            return null;
        }
        
        // Check if the value looks like it's encrypted
        if (str_starts_with($value, 'encrypted:')) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
                // If decryption fails, return the value as is
                return $value;
            }
        }
        
        return $value;
    }

    /**
     * Set the secret key (automatically encrypts)
     */
    public function setApiSecretKeyAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['api_secret_key'] = null;
            return;
        }
        
        // Only encrypt if it's not already encrypted
        if (!str_starts_with($value, 'encrypted:')) {
            $this->attributes['api_secret_key'] = 'encrypted:' . encrypt($value);
        } else {
            $this->attributes['api_secret_key'] = $value;
        }
    }

    /**
     * Get the webhook secret (automatically decrypts if encrypted)
     */
    public function getWebhookSecretAttribute($value)
    {
        if (empty($value)) {
            return null;
        }
        
        if (str_starts_with($value, 'encrypted:')) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
                return $value;
            }
        }
        
        return $value;
    }

    /**
     * Set the webhook secret (automatically encrypts)
     */
    public function setWebhookSecretAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['webhook_secret'] = null;
            return;
        }
        
        if (!str_starts_with($value, 'encrypted:')) {
            $this->attributes['webhook_secret'] = 'encrypted:' . encrypt($value);
        } else {
            $this->attributes['webhook_secret'] = $value;
        }
    }
}