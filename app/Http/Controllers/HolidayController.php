<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\User;
use App\Models\TeamUser;
use App\Models\Position;
use App\Models\Department;
use Carbon\Carbon;
use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use Illuminate\Database\Eloquent\Builder;

class HolidayController extends Controller
{
    public static function getUser($id_user)
    {
        $user = User::findOrFail($id_user);
        $prefixes = [
            'Female' => [
                'Single' => 'Mlle',
                'Married' => 'Mme',
                'Divorce' => 'Mme',
                'Widow' => 'Mme',
            ],
            'Male' => [
                'Single' => 'Mr',
                'Married' => 'Mr',
                'Divorce' => 'Mr',
                'Widow' => 'Mr',
            ],
        ];

        $sex = $prefixes[$user['sex']][$user['Family_situation']] ?? null;
        
        $now = Carbon::now();
        $date_actuelle = $now->year;

        return [$user, $sex, $date_actuelle];
    }

    public static function getLeader($id_user)
    {
        $teamIds = TeamUser::where('user_id', $id_user)->pluck('team_id');
    
        if($teamIds->isEmpty()){
            return [];
        }
    
        $teamLeaders = TeamUser::whereIn('team_id', $teamIds)
            ->with('user')
            ->get()
            ->unique('user_id')
            ->pluck('user');
    
        return $teamLeaders;
    }
    

    public static function getChiefDepartement($id_user)
    {
        $userDepartments = User::where([
            ['status', '=', 'active'],
            ['id', '=', $id_user],
        ])->with(['teams.team.department'])->get();
    
        $chefDepartments = [];
    
        foreach ($userDepartments[0]['teams'] as $team) {
            $department = $team['team']['department'];
            $chefDepartmentId = $department['department_chief'];
    
            $chefDep = User::findOrFail($chefDepartmentId);
            $chefDepartments[] = $chefDep;
        }
    
        return array_values(array_unique($chefDepartments));
    }
    
    public static function getAllGerants()
    {
        $gerantUsers = Position::where([
            ['status', '=', 'active'],
            ['job_name', '=', 'Gérant']
        ])->with(['users.user' => fn($query) => $query->where('status', '=', 'active')])->get();
    
        $gerants = $gerantUsers->flatMap(function ($position) {
            return $position['users']->pluck('user');
        });
    
        return $gerants->unique()->values();
    }

    public static function get_ids_leaders($id)
    {
        $leader = HolidayController::getLeader($id);

        $id_leaders = [];
        foreach ($leader as $l) {
            array_push($id_leaders,$l['id']);
        }
       
        return $id_leaders;
    }

    public static function get_ids_department_chief($id)
    {
        $department_chief = HolidayController::getChiefDepartement($id);
        $id_department_chief = [];
        foreach ($department_chief as $c) {
            array_push($id_department_chief,$c['id']);
        }
        return $id_department_chief;
    }

    public static function get_ids_gerants()
    {
        $gerant = HolidayController::getAllGerants();
        $id_gerants = [];
        foreach ($gerant as $g) {
            array_push($id_gerants,$g['id']);
        }
        return $id_gerants;
    }
    
    public function getHolidayUser($id)
    {
        $responsables = array_values(array_unique(array_merge(
            HolidayController::get_ids_leaders($id),
            HolidayController::get_ids_department_chief($id),
            HolidayController::get_ids_gerants()
        )));
    
        $responsables = array_filter($responsables, fn($resp) => $resp != $id);
    
        $holidays = Holiday::getAllHolidayUser($id);
    
        foreach ($holidays as &$conge) {
            $list = collect($conge['histories'])
                ->reject(fn($c) => $c['is_rejected_prov'] != 0)
                ->map(function ($c) {
                    return $c['status'] == 'Rejet provisoire' ? -1 : ($c['status'] == 'Rejet définitif' ? 'x' : $c['id_responsible']);
                });
    
            $rest = count($responsables) - $list->unique()->count();
    
            $conge['rest'] = $rest;
            $conge['nb_responsable'] = count($responsables);
            $conge['nb_acceptation'] = $list->unique()->values()->all();
        }
    
        return $holidays;
    }

    public function getHistoriqueHolidayUser($id_user)
    {
        return Holiday::where('user_id', $id_user)
            ->whereNotIn('status', ['Envoyé', 'En cours', 'Rejet provisoire'])
            ->with([
                'histories' => function ($query) use ($id_user) {
                    $query->where('id_responsible', '!=', $id_user);
                },
                'user'
            ])
            ->get()
            ->each(function ($conge) {
                $conge->date = Carbon::parse($conge->date)->format('d M Y');
    
                $conge->histories->each(function ($history) {
                    $responsable = User::findOrFail($history->id_responsible);
                    $history->date = Carbon::parse($history->created_at)->format('d M Y');
                    $history->fullName = $responsable->last_name . ' ' . $responsable->first_name;
                });
            });
    }


    public static function test_Leader_ChefDep_Gerant($id_user)
    {
        //retourner l'utilisateur eli authentifier est ce que howa leader wela chef dep wela gerant
        $leaders = HolidayController::getLeader($id_user);
        $test_leader = 0;
        foreach ($leaders as $leader) {
            if($leader['id'] == $id_user){
                $test_leader = 1;
            }
        }

        $department_chief = Department::where('department_chief','=',$id_user)->get();
        $test_chefDep = 0;
             if(count($department_chief) != 0){
                $test_chefDep = 1;
            }

        $gerants = HolidayController::getAllGerants();
        $test_gerant = 0;
        foreach ($gerants as $gerant) {
            if($gerant['id'] == $id_user){
                $test_gerant = 1;

            }
        }
        return ["leader" => $test_leader,"department_chief" =>$test_chefDep,"gerant" =>$test_gerant];
    }

    public function getAllHolidayLeader($id_auth)
    {
        $teamIds = TeamUser::where('user_id', $id_auth)
            ->where('is_leader', 1)
            ->pluck('team_id')
            ->toArray();
    
        $holidays = Holiday::where('level', 1)
            ->whereHas('user.teams', function ($query) use ($teamIds) {
                $query->whereIn('team_id', $teamIds);
            })
            ->with([
                'histories' => function ($query) use ($id_auth) {
                    $query->where([
                        ['is_rejected_prov', '=', 0],
                        ['level', '=', 1],
                        ['id_responsible', '=', $id_auth],
                    ]);
                },
                'user.teams',
            ])
            ->withCount(['histories as history_count' => function ($query) use ($id_auth) {
                $query->where([
                    ['is_rejected_prov', '=', 0],
                    ['level', '=', 1],
                    ['id_responsible', '=', $id_auth],
                ]);
            }])
            ->get();
    
        $listHolidaysFinal = $holidays->filter(function ($conge) {
            return $conge->history_count == 0 || $conge->histories->contains('id_responsible', auth()->id());
        });
    
        return array_values($listHolidaysFinal->unique('id')->toArray());
    }

    public function getAllHolidayChefDepartment($id_auth)
    {
        $listHolidays = Holiday::where('level', 2)
            ->whereHas('user.teams.team.department', function ($query) use ($id_auth) {
                $query->where('department_chief', $id_auth);
            })
            ->with([
                'histories' => function ($query) use ($id_auth) {
                    $query->where([
                        ['is_rejected_prov', '=', 0],
                        ['level', '=', 2],
                        ['id_responsible', '=', $id_auth],
                    ]);
                },
                'user.teams.team.department',
            ])->get();
    
        $listHolidaysFinal = $listHolidays->filter(function ($conge) use ($id_auth) {
            return count($conge['histories']) == 0 || $conge['histories']->contains('id_responsible', $id_auth);
        });
    
        return array_values($listHolidaysFinal->unique('id')->toArray());
    }
    

    public function getAllHolidayGerant($id_auth)
    {
        // afficher list pour le gerant !!!!
        $result_gerants = Holiday::where([['level','=',3]])->with([
            'histories' => fn($query) => $query->where([['is_rejected_prov', '=', 0],['level', '=', 3],['id_responsible', '=', $id_auth]]),
            'user'
        ])->get();

        $rep_responsable = false;

        $List_holidays_final = [];

        foreach($result_gerants as $conge) {
            if(count($conge['histories']) == 0){
                array_push($List_holidays_final,$conge);
            }else{
                foreach ($conge['histories'] as $history) {
                    if($history['id_responsible'] == $id_auth){
                            $rep_responsable = true;
                    }else{
                        array_push($List_holidays_final,$conge);
                    }
                }
            }
        }
        return $result = array_values(array_unique($List_holidays_final));
    }

    public function getAllHoliday($id_auth)
    {
        $result = [];
        // ken user auth = leader !!
        $test_fonction = HolidayController::test_Leader_ChefDep_Gerant($id_auth);

        if($test_fonction['leader'] == 1 && $test_fonction['department_chief'] == 0 && $test_fonction['gerant'] == 0){
           $result = HolidayController::getAllHolidayLeader($id_auth);
        }
        if($test_fonction['leader'] == 0 && $test_fonction['department_chief'] == 1 && $test_fonction['gerant'] == 0){
            $result = HolidayController::getAllHolidayChefDepartment($id_auth);
        }
        if($test_fonction['leader'] == 0 && $test_fonction['department_chief'] == 0 && $test_fonction['gerant'] == 1){
            $result = HolidayController::getAllHolidayGerant($id_auth);
        }

        if($test_fonction['leader'] == 1 && $test_fonction['department_chief'] == 1 && $test_fonction['gerant'] == 0){
            $result_1 = HolidayController::getAllHolidayLeader($id_auth);
            $result_2 = HolidayController::getAllHolidayChefDepartment($id_auth);
            $result = array_merge($result_1, $result_2);
        }

         if($test_fonction['leader'] == 0 && $test_fonction['department_chief'] == 1 && $test_fonction['gerant'] == 1){
            $result_1 = HolidayController::getAllHolidayGerant($id_auth);
            $result_2 = HolidayController::getAllHolidayChefDepartment($id_auth);
            $result = array_merge($result_1, $result_2);
         }

         if($test_fonction['leader'] == 1 && $test_fonction['department_chief'] == 0 && $test_fonction['gerant'] == 1){
            $result_1 = HolidayController::getAllHolidayLeader($id_auth);
            $result_2 = HolidayController::getAllHolidayGerant($id_auth);
            $result = array_merge($result_1, $result_2);
         }

         if($test_fonction['leader'] == 1 && $test_fonction['gerant'] == 1 && $test_fonction['department_chief'] == 1){
            $result_1 = HolidayController::getAllHolidayLeader($id_auth);
            $result_2 = HolidayController::getAllHolidayGerant($id_auth);
            $result_3 = HolidayController::getAllHolidayChefDepartment($id_auth);
            $result = array_merge($result_1, $result_2, $result_3);
         }


        return response()->json([
            'conge' => $result,
            'success' => true
        ], 200);
    }

    public function getHistoriqueHolidayLeader($id_auth)
    {
        $List_conges = [];
        // afficher list pour le responsable !!!!
        $List_team = TeamUser::where([['user_id','=',$id_auth],['is_leader','=',1]])->get();
        $team_id = [];
        // return id team eli appartient liha el id_auth kenou leader ala equipe !!!
        if(count($List_team) != 0){
            foreach ($List_team as $team) {
                array_push($team_id,$team['team_id']);
            }
        }else{
            $team_id =  null;
        }

        $conges = Holiday::where([['status','!=','Envoye'],['user_id','!=',$id_auth]])->with([
            'histories' ,
            'user'=> fn($query) => $query->with([
                'teams'=> ([
                    'team'=> fn($query) => $query->where([['status','=','active']])->with([
                        'department'=> fn($query) => $query->where([['status','=','active']])->pluck('id')
                        ])
                ])
            ])
        ])->get();

       $user_team = [];

        foreach ($conges as $conge) {

            foreach ($conge['user']['teams'] as $team) {
                if($team_id != null){
                    if(in_array($team['team_id'],$team_id)){
                        array_push($List_conges,$conge);
                        $user_team = array_values(array_unique($List_conges));
                    }
                }
            }
        }

        $List_conges_final = [];

        foreach ($user_team as $conge) {
            $tab_ids = [];
            $date_final = Carbon::parse($conge->date)->format('d M Y');
            $conge['date'] = $date_final;
            if(count($conge['histories']) != 0){
                $List_History = $conge['histories'];
                $conge['histories'] = [];
                foreach ($List_History as $history) {

                    $responsable = User::findOrFail($history['id_responsible']);
                    $history['fullName'] = $responsable['last_name'] .' '. $responsable['first_name'];

                    $date = Carbon::parse($history->created_at)->format('d M Y');
                    $history['date'] = $date;
                    array_push($tab_ids,$history['id_responsible']);
                    if($history['id_responsible'] == $id_auth){
                         array_push($List_conges_final,$conge);
                    }
                    $conge['tab_ids_Final'] = array_values(array_unique($tab_ids));
                }

            }


        }

        return array_values(array_unique($List_conges_final));
    }

    public function getHistoriqueHolidayChefDepartment($id_auth)
    {
        $List_conges = [];
        $List_department = Department::where([['department_chief','=',$id_auth]])->get();
        $List_id_department = [];

        // return id team eli appartient liha el id_auth kenou leader ala equipe !!!
        if(count($List_department) != 0){
            foreach ($List_department as $dep) {
                array_push($List_id_department,$dep['id']);
            }
        }else{
            $List_id_department =  null;
        }

        $conges = Holiday::where([['level','!=',1],['status','!=','Envoye'],['user_id','!=',$id_auth]])->with([
            'histories',
            'user'=> fn($query) => $query->with([
                'teams'=> ([
                    'team'=> fn($query) => $query->where([['status','=','active']])->with([
                        'department'=> fn($query) => $query->where([['status','=','active']])->pluck('id')
                        ])
                ])
            ])
          ])->get();

            $user_dep = [];

            foreach ($conges as $conge) {
                foreach ($conge['user']['teams'] as $team) {
                    if($team['team'] != null && $List_id_department != [] ){
                        if(in_array($team['team']['department']['id'],$List_id_department)){
                            array_push($List_conges,$conge);
                            $user_dep = array_values(array_unique($List_conges));
                        }
                    }
                }
            }

        $List_conges_final = [];
        $rep_responsable = false;

        foreach($user_dep as $conge) {
            $date_final = Carbon::parse($conge->date)->format('d M Y');
            $conge['date'] = $date_final;
            if(count($conge['histories']) != 0){
                foreach ($conge['histories'] as $history) {
                        $responsable = User::findOrFail($history['id_responsible']);
                        $history['fullName'] = $responsable['last_name'] .' '. $responsable['first_name'];
                        $date = Carbon::parse($history->created_at)->format('d M Y');
                        $history['date'] = $date;
                        if($history['id_responsible'] == $id_auth){
                            array_push($List_conges_final,$conge);
                        }
                }
            }
        }
        return array_values(array_unique($List_conges_final));
    }

    public function getHistoriqueHolidayGerant($id_auth)
    {
        // afficher list pour le gerant !!!!
        $result_gerants = Holiday::where([['level','!=',1],['level','!=',2],['status','!=','Envoye'],['user_id','!=',$id_auth]])->orWhere([['level','!=',2],['status','!=','Envoye'],['user_id','!=',$id_auth]])->with([
            'histories',
            'user'=> fn($query) => $query->with([
                'teams'=> ([
                    'team'=> fn($query) => $query->where([['status','=','active']])->with([
                        'department'=> fn($query) => $query->where([['status','=','active']])->pluck('id')
                        ])
                ])
            ])
        ])->get();

        $List_conges_final = [];

        foreach($result_gerants as $conge) {
            $date_final = Carbon::parse($conge->date)->format('d M Y');
            $conge['date'] = $date_final;
            if(count($conge['histories']) != 0){
                foreach ($conge['histories'] as $history) {
                        $responsable = User::findOrFail($history['id_responsible']);
                        $history['fullName'] = $responsable['last_name'] .' '. $responsable['first_name'];
                        $date = Carbon::parse($history->created_at)->format('d M Y');
                        $history['date'] = $date;
                    if($history['id_responsible'] == $id_auth){
                        array_push($List_conges_final,$conge);
                    }
                }
            }
        }
        return array_values(array_unique($List_conges_final));
    }

    public function getHistoriqueHoliday($id_auth)
    {
        $result = [];
        // ken user auth = leader !!
        $test_fonction = HolidayController::test_Leader_ChefDep_Gerant($id_auth);

        if($test_fonction['leader'] == 1 && $test_fonction['department_chief'] == 0 && $test_fonction['gerant'] == 0){
           $result = HolidayController::getHistoriqueHolidayLeader($id_auth);
        }
        if($test_fonction['leader'] == 0 && $test_fonction['department_chief'] == 1 && $test_fonction['gerant'] == 0){
            $result = HolidayController::getHistoriqueHolidayChefDepartment($id_auth);
        }
        if($test_fonction['leader'] == 0 && $test_fonction['department_chief'] == 0 && $test_fonction['gerant'] == 1){
            $result = HolidayController::getHistoriqueHolidayGerant($id_auth);
        }
        if($test_fonction['leader'] == 1 && $test_fonction['department_chief'] == 1 && $test_fonction['gerant'] == 0){
            $result_1 = HolidayController::getHistoriqueHolidayLeader($id_auth);
            $result_2 = HolidayController::getHistoriqueHolidayChefDepartment($id_auth);
            $result = array_values(array_unique(array_merge($result_1, $result_2)));
        }
         if($test_fonction['leader'] == 0 && $test_fonction['department_chief'] == 1 && $test_fonction['gerant'] == 1){
             $result_1 = HolidayController::getHistoriqueHolidayGerant($id_auth);
             $result_2 = HolidayController::getHistoriqueHolidayChefDepartment($id_auth);
             $result = array_values(array_unique(array_merge($result_1, $result_2)));

         }
         if($test_fonction['leader'] == 1 && $test_fonction['department_chief'] == 0 && $test_fonction['gerant'] == 1){
            $result_1 = HolidayController::getHistoriqueHolidayLeader($id_auth);
            $result_2 = HolidayController::getHistoriqueHolidayGerant($id_auth);
            $result = array_values(array_unique(array_merge($result_1, $result_2)));
         }
         if($test_fonction['leader'] == 1 && $test_fonction['gerant'] == 1 && $test_fonction['department_chief'] == 1){
            $result_1 = HolidayController::getHistoriqueHolidayLeader($id_auth);
            $result_2 = HolidayController::getHistoriqueHolidayGerant($id_auth);
            $result_3 = HolidayController::getHistoriqueHolidayChefDepartment($id_auth);
            $result = array_values(array_unique(array_merge($result_1, $result_2,$result_3)));
         }

        return response()->json([
            'conge' => $result,
            'success' => true
        ], 200);
    }
}
