<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeSubject extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employee_subjects';

    protected $fillable = [
        'employee_id',
        'subject_id',
        'school_id'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}
