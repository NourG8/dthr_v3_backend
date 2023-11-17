<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use App\Http\Requests\Department\AddDepartmentRequest;

class DepartmentController extends Controller
{
    public function getAllDepartments()
    {
        $departments = Department::where('status', 'active')->get();

        return $this->successResponse($departments);
    }

    public function addDepartment(AddDepartmentRequest $request)
    {
        $validatedData = $request->validated();
        $department = Department::create(array_merge($validatedData));
    
        return $this->successResponse($department);
    }

    public function editDepartment(AddDepartmentRequest $request,$id)
    {
        $dep = Department::findOrFail($id);
        $validatedData = $request->validated();
        $dep->update($validatedData);

        return $this->successResponse($dep);
    }

    public function destroyDepartment($id)
    {
        $department = Department::findOrFail($id);
        $department->teams()->delete();
        $department->delete();
    
        return $this->successResponse($department);
    }

    public function archiveDepartment($id)
    {
        $department = Department::findOrFail($id);

        $numberOfActveUsersInDep = User::getNumberOfActiveUsersInDepartment($id);
        $numberOfActiveTeamsInDep = Team::getNumberOfActiveTeamsInDepartment($id);

        if ($numberOfActveUsersInDep == 0 && $numberOfActiveTeamsInDep != 0) {
            $department->teams()->update(['status' => 'inactive_dep']);
            $department->update(['status' => 'inactive']);
        }elseif($numberOfActveUsersInDep == 0 && $numberOfActiveTeamsInDep == 0) {
            $department->update(['status' => 'inactive']);
        }else {
            return $this->errorResponse("You cannot archive this department",404);
        }
        
        return $this->successResponse($department);
    }

    public function reactivateDepartment($id)
    {
        $department = Department::findOrFail($id);

        $numberOfActiveUsersInDep = User::getNumberOfActiveUsersInDepartment($id);
        $numberOfInactiveTeamsInDep = Team::getNumberOfInactiveTeamsInDepartment($id);
        // return [$numberOfActiveUsersInDep , $numberOfInactiveTeamsInDep ];

        if ($department->status == 'inactive' && $numberOfActiveUsersInDep == 0 && $numberOfInactiveTeamsInDep != 0) {
            $department->teams()->update(['status' => 'active']);
            $department->update(['status' => 'active']);
        } elseif ($department->status == 'inactive' && $numberOfActiveUsersInDep == 0 && $numberOfInactiveTeamsInDep == 0) {
            $department->update(['status' => 'active']);
        }

        return $this->successResponse($department);
    }

    public function getUsersActiveDepartment($id_dep)
    {
        $activeUsers = User::whereHas('teams', function ($query) use ($id_dep) {
            $query->whereHas('team', function ($teamQuery) use ($id_dep) {
                $teamQuery->where('department_id', $id_dep)->where('status', 'active');
            })->whereHas('team.department', function ($departmentQuery) {
                $departmentQuery->where('status', 'active');
            });
        })->where('status', 'active')->get();

        return $this->successResponse($activeUsers);
    }
        
    public function getArchivedDepartment()
    {
        $archivedDepartments = Department::where('status', 'inactive')->get();

        return $this->successResponse($archivedDepartments);
    }

    public function getNb_team_in_dep($id)
    {
       $numberActiveTeamInDep = Team::getNumberOfActiveTeamsInDepartment($id);

       return $this->successResponse($numberActiveTeamInDep);
    }

    public function getNb_team_in_dep_Archive($id)
    {
        $numberInactiveTeamInDep = Team::getNumberOfInactiveTeamsInDepartment($id);

        return $this->successResponse($numberInactiveTeamInDep);
    }

    public function getNb_Users_in_dep($id)
    {
        $numberOfActiveUsersInDep = User::getNumberOfActiveUsersInDepartment($id);
        return $this->successResponse($numberOfActiveUsersInDep);
    }
}
