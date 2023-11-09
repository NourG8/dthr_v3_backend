<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PositionUser extends Model
{
    use HasFactory;
    
    protected $guarded = [];
    protected $with = ['position'];

    public function position()
    {
        return $this->hasOne(Position::class,'id','position_id')->where('is_deleted', false);
    }

    public function user()
    {
        return $this->hasOne(User::class,'user_id','id');
    }
}
