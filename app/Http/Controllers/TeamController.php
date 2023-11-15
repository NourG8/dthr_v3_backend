<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamUser;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\Team\AddTeamRequest;
use Carbon\Carbon;

class TeamController extends Controller
{
    public function getTeams()
    {
        $teams = Team::where('status', 'active')->with(['users'])->get();

        return $this->successResponse($teams);
    }

    public function getUsersInTeams($id)
    {
        $teams = Team::where([['status','=','active'],['id','=',$id]])
        ->with([
            'users' => function ($query) {
                $query->with(['user'])
                    ->whereHas('user', function ($query) {
                        $query->where('status', 'active');
                    });
            }
        ])->get();

        return $this->successResponse($teams);
    }

    public function getAllArchiveTeams()
    {
        $teams = Team::where('status','=','inactive')->get();

        return $this->successResponse($teams);
    }

    public function addTeams(AddTeamRequest $request)
    {
        $validatedData = $request->validated();
    
        $team = Team::create([
            'name' => $validatedData['name'],
            'department_id' => $validatedData['department_id'],
            'description' => $validatedData['description'],
            'status' => 'active'
        ]);
    
        $team->users()->create([
            'user_id' => $validatedData['user_id'],
            'integration_date' => Carbon::now()->toDateTimeString(),
            'is_leader' => 1
        ]);

        return $this->successResponse($team);
    }

    public function updateTeams(AddTeamRequest $request,$id_team)
    {
        $validatedData = $request->validated();

        $team = Team::findOrFail($id_team);
        $team->update([
            'name' => $validatedData['name'],
            'description' => $validatedData['description'],
            'department_id' => $validatedData['department_id'],
        ]);

          $team_leader = TeamUser::where([
            'team_id' => $id_team,
            'is_leader' => 1
          ])->first();

        if ($team_leader && $team_leader->user_id != $validatedData['user_id']) {
            $team_leader->delete();
    
            TeamUser::create([
                'team_id' => $team->id,
                'user_id' => $validatedData['user_id'],
                'integration_date' => Carbon::now()->toDateTimeString(),
                'is_leader' => 1
            ]);
        }

        return $this->successResponse([ 'team' => $team,  'user' => $team_leader ]);
    }

    public function deleteTeams($id_team)
    {
        $team = Team::findOrFail($id_team);
        $team->delete();

        return $this->successResponse( $team );
    }

    public function deleteUserTeams($id_team_user)
    {
        $team_user = TeamUser::findOrFail($id_team_user);
        $team_user->delete();

        return $this->successResponse( $team_user );
    }

    public function desactiverTeams($id_team)
    {
        $team = Team::findOrFail($id_team);
        $team->update(['status' => 'inactive']); 

        return $this->successResponse( $team );
    }

    public function activerTeams($id_team)
    {
        $team = Team::findOrFail($id_team);
        $team->update(['status' => 'active']);  
        
        return $this->successResponse( $team );
    }


}
