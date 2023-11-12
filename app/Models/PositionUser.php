<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PositionUser extends Model
{
    use HasFactory , SoftDeletes;
    
    protected $guarded = [];
    protected $with = ['position'];

    public function position()
    {
        return $this->hasOne(Position::class,'id','position_id');
    }

    public function user()
    {
        return $this->hasOne(User::class,'id','user_id');
    }
}
