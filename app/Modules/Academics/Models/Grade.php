<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grade extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'section_id', 'school_id'];

    public function school() 
    {
        return $this->belongsTo(School::class);
    }
    
    public function section() 
    { 
        return $this->belongsTo(Section::class); 
    }
    
    public function students() 
    { 
        return $this->hasMany(Student::class); 
    }
    
    // FIX THIS: Change from belongsToMany to hasMany
    public function subjects() 
    { 
        return $this->hasMany(Subject::class); 
    }
}