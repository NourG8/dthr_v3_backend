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

    public function documents()
    {
        return $this->hasMany(UserDocument::class, 'user_id', 'id');
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

    public static function get_ids_leaders($id)
    {
        $leader = self::getLeader($id);
        return array_column($leader, 'id');
    }

    public static function get_ids_department_chief($id)
    {
        $department_chief = self::getChiefDepartement($id);
        return array_column($department_chief, 'id');
    }

    public static function get_ids_gerants()
    {
        $gerant = self::getAllGerants();
        $gerantArray = $gerant->toArray();
        return array_column($gerantArray, 'id');
    }

    public static function getLeader($id_user)
    {
        $teamIds = TeamUser::where('user_id', $id_user)->pluck('team_id');

        if ($teamIds->isEmpty()) {
            return [];
        }

        $teamLeaders = TeamUser::whereIn('team_id', $teamIds)
            ->where('is_leader', 1)
            ->with('user')
            ->get()
            ->unique('user_id')
            ->pluck('user');

        return $teamLeaders->toArray();
    }

    public static function getChiefDepartement($id_user)
    {
        $userDepartments = User::where([
            ['status', '=', 'active'],
            ['id', '=', $id_user],
        ])->with(['teams.team.department'])->first();

        $chefDepartments = $userDepartments->teams->map(function ($team) {
            $department = $team->team->department;
            $chefDep = User::findOrFail($department->department_chief);
            return $chefDep;
        });

        return $chefDepartments->all();
    }

    public static function getAllGerants()
    {
        $gerantUsers = Position::where([
            ['status', '=', 'active'],
            ['job_name', '=', 'Gérant']
        ])->with(['users.user' => fn ($query) => $query->where('status', '=', 'active')])->get();

        $gerants = $gerantUsers->flatMap(function ($position) {
            return $position['users']->pluck('user');
        });

        return $gerants->unique()->values();
    }

}
