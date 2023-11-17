<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];
    // protected $with = ['department'];

    public function department()
    {
        return $this->hasOne(Department::class,'id','department_id');
    }

    public function users()
    {
        return $this->hasMany(TeamUser::class);
    }

    public static function getNumberOfInactiveTeamsInDepartment($departmentId)
    {
        return self::where('department_id', $departmentId)
            ->where('status', 'inactive_dep')
            ->whereHas('department', function ($query) {
                $query->where('status', 'inactive');
            })->count();
    }

    public static function getNumberOfActiveTeamsInDepartment($departmentId)
    {
        return self::where("department_id",$departmentId)->where("status","active")->count();
    }

}
