<?php

namespace App\Modules\Academics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Core\Models\School;
use App\Modules\Academics\Models\Grade;


class Subject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'grade_id', 'school_id'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }
   
}

