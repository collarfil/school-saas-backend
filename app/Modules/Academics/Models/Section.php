<?php

namespace App\Modules\Academics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Core\Models\School;
class Section extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name','school_id'];
    public function school(){return $this->belongsTo(School::class);}

}
