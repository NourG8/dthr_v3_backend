<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable , HasRoles , SoftDeletes;   


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

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'motivation' => 'json'
    ];

    public static function getNumberOfActiveUsersInDepartment($departmentId)
    {
        return self::whereHas('teams', function ($query) use ($departmentId) {
            $query->whereHas('team', function ($teamQuery) use ($departmentId) {
                $teamQuery->where('department_id', $departmentId)
                    ->where('status', 'active');
            })
            ->whereHas('team.department', function ($departmentQuery) {
                $departmentQuery->where('status', 'active');
            });
        })
        ->where('status', 'active')
        ->count();
    }


}
