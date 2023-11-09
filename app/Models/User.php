<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable , HasRoles;   


    protected $guarded = [];

    protected $with = ['positions', 'teams'];

    public function positions()
    {
        return $this->hasMany(PositionUser::class);
    }

    public function contracts()
    {
        return $this->hasMany(UserContract::class, 'user_id', 'id');
    }

    public function conges()
    {
        return $this->hasMany(Conge::class);
    }

    public function teams()
    {
        return $this->hasMany(TeamUser::class);
    }

    public function teleworks()
    {
        return $this->hasMany(Telework::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // protected $fillable = [
    //     'name',
    //     'email',
    //     'password',
    // ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'motivation' => 'json'
    ];
}
