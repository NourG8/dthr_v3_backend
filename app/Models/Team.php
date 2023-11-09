<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $with = ['department'];

    public function department()
    {
        return $this->hasOne(Department::class,'id','department_id');
    }
}
