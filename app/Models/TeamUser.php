<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamUser extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];
    // protected $with = ['user','team'];

    public function team()
    {
        return $this->belongsTo(Team::class,'team_id','id')->where('status','active');
    }

    public function user()
    {
        return $this->belongsTo(User::class,'user_id','id')->where('status','active');
    }
}
