<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamUser extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $with = ['team'];

    public function team()
    {
        return $this->hasOne(Team::class,'id','team_id');
    }

    public function user()
    {
        return $this->hasOne(User::class,'user_id','id');
    }
}
