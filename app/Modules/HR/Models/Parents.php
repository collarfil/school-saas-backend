<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\HR\Models\Student;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;


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
