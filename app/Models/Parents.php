<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Parents extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'parents';

    protected $fillable = ['name', 'phone', 'email', 'address', 'school_id'];

    public function school(){return $this->belongsTo(School::class);}
    public function students()
    {
        return $this->hasMany(Student::class, 'parents_id');
    }

}
