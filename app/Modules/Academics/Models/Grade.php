<?php

namespace App\Modules\Academics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Core\Models\School;
use App\Modules\Academics\Models\Section;
use App\Modules\HR\Models\Student;
use App\Modules\Academics\Models\Subject;
use App\Modules\Core\Models\User;

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