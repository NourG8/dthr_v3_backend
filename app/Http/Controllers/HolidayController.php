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

/* 
 public function ResponsableAddConge($id)
    {
        $this->user = CongeController::getUser($id);

        $this->leader = CongeController::getLeader($id);

        $this->gerants = CongeController::getAllGerants($id);

        $this->chef_dep = array_values(array_unique(CongeController::getChefDepartement($id)));

        if(count($this->chef_dep) == 0){
            $ids_gerants = CongeController::get_ids_gerants();
            if(in_array($id,$ids_gerants)){
                $conge_history = new CongeHistory();
                $conge_history->id_responsable = $id;
                $conge_history->status = "Accepter";
                $conge_history->is_rejected_prov = 0;
                $conge_history->is_archive = 0;
                $conge_history->level = 3;
                $conge_history->conge_id = $this->conge['id'];
                $conge_history->save();

                $gerants = $this->gerants;
                $this->gerants = [];
                foreach($gerants as $g) {
                    if($g['id'] != $id){
                      array_push($this->gerants,$g);
                    }
                }
            }

                $conge = Conge::findOrFail($this->conge['id']);

                if(count($this->gerants) == 0){
                    $conge->status = "Accepter";
                    $conge->level = 3;
                    $conge->save();
                }else{
                    $conge->level = 3;
                    $conge->save();
                    Mail::send('conge.InfoEmail', ['user' => $this->user], function($message) {
                        foreach ($this->gerants as $gerant) {
                            $message->to($gerant['email']);
                        }
                        $message->subject('Leave request');
                    });
                }
                $this->conge = $conge;
            }else{
                $ids_leaders = CongeController::get_ids_leaders($id);

                if(in_array($id,$ids_leaders)){
                    $conge_history = new CongeHistory();
                    $conge_history->id_responsable = $id;
                    $conge_history->status = "Accepter";
                    $conge_history->is_rejected_prov = 0;
                    $conge_history->is_archive = 0;
                    $conge_history->level = 1;
                    $conge_history->conge_id = $this->conge['id'];
                    $conge_history->save();

                    $leader = $this->leader;
                    $this->leader = [];
                    foreach ($leader as $l) {
                        if($l['id'] != $id){
                        array_push($this->leader,$l);
                        }
                    }
                }

                if(count($this->leader) == 0){
                    $conge = Conge::findOrFail($this->conge['id']);
                    $conge->level = 2;
                    $conge->save();

                    Mail::send('conge.InfoEmail', ['user' => $this->user], function($message) {
                        foreach ($this->chef_dep as $chef_dep) {
                           $message->to($chef_dep['email']);
                        }
                        $message->subject('Leave request');
                    });
                    $this->conge = $conge;
                }else{
                    Mail::send('conge.InfoEmail', ['user' => $this->user], function($message) {
                        foreach ($this->leader as $leader) {
                          $message->to($leader['email']);
                        }
                        $message->subject('Leave request');
                    });
                }
            }
    }

    public function AddConge(Request $request,$id)
    {
        $this->conge = new Conge();
        $this->conge->type = $request->input('type');
        $this->conge->raison = $request->input('raison');
        $this->conge->dates = $request->input('dates');
        $this->conge->level = 1;
        $this->conge->date = Carbon::now();
        $this->conge->is_deleted = 0;
        $this->conge->status = "EnvoyÃ©";
        $this->conge->user_id = $id;
        $this->conge->save();

        CongeController::ResponsableAddConge($id);

        return response()->json([
            'conge' => $this->conge,
            'success' => true
        ], 200);

    }

    public function updateConge(Request $request,$id)
    {
        $this->conge = Conge::findOrFail($id);

        $conges_history = CongeHistory::where('conge_id','=',$id)->update(['is_rejected_prov' =>1,'is_archive'=> 1]);

        $this->conge->type = $request->input('type');
        $this->conge->raison = $request->input('raison');
        $this->conge->dates = $request->input('dates');
        $this->conge->status = "EnvoyÃ©";
        $this->conge->is_deleted = 0;
        $this->conge->level = 1;
        $this->conge->save();

        CongeController::ResponsableAddConge($this->conge->user_id);

        return response()->json([
            'conge' => $this->conge,
            'success' => true
        ], 200);
    }

    public function deleteConge($id)
    {
        $conge = Conge::findOrFail($id);
        $conge->is_deleted = 1;
        $conge->save();
        return response()->json([
            'conge' => $conge,
            'success' => true
        ], 200);
    }

    public function AnnulerConge($id)
    {
        $conge = Conge::findOrFail($id);
        $conge->status = "Annuler";
        $conge->save();

        $this->result = "demande annuler";

        $this->user = User::findOrFail($conge->user_id);

        if($conge->level == 1){
            $this->list_responsable = CongeController::getLeader($conge['user_id']);
        }else if($conge->level == 2){
            $this->list_responsable = CongeController::getChefDepartement($conge['user_id']);
        }else if ($conge->level == 3){
            $this->list_responsable = CongeController::getAllGerants();
        }

        if(count($this->list_responsable) != 0){
            Mail::send('conge.AnnulerConge', ['conge' => $conge, 'user' =>  $this->user], function($message) {
                foreach ($this->list_responsable as $resp) {
                    $message->to($resp['email']);
                }
                $message->subject('Response following the cancellation of leaving');
            });
        }

        return response()->json([
            'conge' => $conge,
            'success' => true
        ], 200);
    }

    public function RejetDefinitive(Request $request,$id_conge)
    {
        $responsable = Auth::user();

        $conge = Conge::findOrFail($id_conge);
        $conge->status = "Rejet dÃ©finitif";
        $conge->save();

        $conge_history = new CongeHistory();
        $conge_history->id_responsable = $responsable['id'];
        $conge_history->status = "Rejet dÃ©finitif";
        $conge_history->is_rejected_prov = 0;
        $conge_history->is_archive = 0;
        $conge_history->raison_reject = $request->raison_reject;
        $conge_history->level = $conge->level;
        $conge_history->conge_id = $id_conge;
        $conge_history->save();

        $this->user = CongeController::getUser($conge['user_id']);

        $this->result = "Rejet dÃ©finitive";

        Mail::send('conge.RejetDefinitive', ['result' => $conge_history->raison_reject, 'conge'=> $conge, 'user' =>  $this->user[0]], function($message) {
            $message->to($this->user[0]['email']);
            $message->subject('Request rejected');
        });

        return response()->json([
            'conge' => $conge,
            'success' => true
        ], 200);
    }

    public function RejetProvisoire(Request $request,$id_conge)
    {
        $responsable = Auth::user();
        $conge = Conge::findOrFail($id_conge);
        $conge->status = "Rejet provisoire";
        $conge->save();

        $conge_history = new CongeHistory();
        $conge_history->id_responsable = $responsable['id'];
        $conge_history->status = "Rejet provisoire";
        $conge_history->is_archive = 0;
        $conge_history->is_rejected_prov = 0;
        $conge_history->raison_reject = $request->raison_reject;
        $conge_history->level = $conge->level;
        $conge_history->conge_id = $id_conge;
        $conge_history->save();

        $this->user = User::findOrFail($conge->user_id);

        $this->result = "Rejet provisoire";

        Mail::send('conge.RejetProvisoire', ['result' => $conge_history->raison_reject, 'conge'=> $conge, 'user' =>  $this->user], function($message) {
            $message->to($this->user['email']);
            $message->subject('Provisionally refusal of your leave request');
        });

        return response()->json([
            'conge' => $conge,
            'success' => true
        ], 200);
    }

    public function acceptCongeLeader($id_conge)
    {
        $leader = Auth::user();

        $List_conges = [];

        $conge = Conge::findOrFail($id_conge);

        $ids_leaders = CongeController::get_ids_leaders($conge['user_id']);

        if(in_array($leader['id'],$ids_leaders)){
            $conge_history = new CongeHistory();
            $conge_history->id_responsable = $leader['id'];
            $conge_history->status = "Accepter";
            $conge_history->is_rejected_prov = 0;
            $conge_history->is_archive = 0;
            $conge_history->level = 1;
            $conge_history->conge_id = $id_conge;
            $conge_history->save();

            $conge->status="En cours";
            $conge->save();

            $allConges = Conge::where([['is_deleted', '=', 0],['status','=','EnvoyÃ©'],['level','=','1'],['id','=',$id_conge]])->orWhere([['is_deleted', '=', 0],['status','=','En cours'],['level','=','1'],['id','=',$id_conge]])->with([
                'histories' => fn($query) => $query->where([['is_rejected_prov', '=', 0],['level', '=', 1],['status','=','Accepter'],['conge_id', '=', $id_conge]]),
            ])->get();

            if(count($allConges) != 0){
                foreach($allConges as $conge) {
                    array_push($List_conges,$conge);
                }
            }

            $Leaders = CongeController::getLeader($conge['user_id']);
            $chef_dep = CongeController::getChefDepartement($conge['user_id']);
            $this->user = CongeController::getUser($conge['user_id']);

            $this->chef_dep = array_diff($chef_dep, $Leaders);

            if(count($List_conges) != 0){
                if(count($List_conges[0]['histories']) == count($Leaders) ){
                    $now = Carbon::now();
                    $conge = DB::table('conges')
                    ->select('conges.*')
                    ->where([
                        ['id', '=',$id_conge],
                        ['is_deleted', '=', 0],
                        ['level', '=', 1],
                    ])->update(['level' => 2,'date' => $now]);

                        if(count($this->chef_dep) != 0){
                            Mail::send('conge.InfoEmail', ['user' => $this->user], function($message) {
                                foreach ($this->chef_dep as $chef) {
                                    $message->to($chef['email']);
                                }
                                $message->subject('Leave request');
                            });
                        }
                }
            }
        }
        return $List_conges;
    }

    public function acceptCongeChefDep($id_conge)
    {
        $this->user = Auth::user();

        $conge = Conge::findOrFail($id_conge);

        $List_conges = [];

        $ids_chef_dep = CongeController::get_ids_chef_dep($conge['user_id']);

        if(in_array($this->user['id'],$ids_chef_dep)){
            $conge_history = new CongeHistory();
            $conge_history->id_responsable = $this->user['id'];
            $conge_history->status = "Accepter";
            $conge_history->is_rejected_prov = 0;
            $conge_history->is_archive = 0;
            $conge_history->level = 2;
            $conge_history->conge_id = $id_conge;
            $conge_history->save();

            $conge->status="En cours";
            $conge->save();

            $allConges = Conge::where([['is_deleted', '=', 0],['status','=','En cours'],['level','=','2'],['id','=',$id_conge]])->with([
                'histories' => fn($query) => $query->where([['is_rejected_prov', '=', 0],['level', '=', 2],['status','=','Accepter'],['conge_id', '=', $id_conge]]),
            ])->get();

            if(count($allConges) != 0){
                foreach($allConges as $conge) {
                    array_push($List_conges,$conge);
                }
            }
            $chef_dep = CongeController::getChefDepartement($conge['user_id']);
            $gerants = CongeController::getAllGerants();
            $this->user = CongeController::getUser($conge['user_id']);

            $this->gerants = array_diff($gerants, $chef_dep);

            if(count($List_conges) != 0){
                if(count($List_conges[0]['histories']) == count($chef_dep)){
                    $now = Carbon::now();
                    $conge = DB::table('conges')
                    ->select('conges.*')
                    ->where([
                        ['id', '=',$id_conge],
                        ['is_deleted', '=', 0],
                        ['level', '=', 2],
                    ])->update(['level' => 3,'date' => $now]);

                    if(count($this->gerants) != 0){
                        Mail::send('conge.InfoEmail', ['user' => $this->user], function($message) {
                            foreach ($this->gerants as $gerant) {
                                $message->to($gerant['email']);
                            }
                            $message->subject('Leave request');
                        });
                    }

                }
            }
        }

        return $List_conges;
    }

    public function acceptCongeGerant($id_conge)
    {
        $leader = Auth::user();

        $conge_history = new CongeHistory();
        $conge_history->id_responsable = $leader['id'];
        $conge_history->status = "Accepter";
        $conge_history->is_rejected_prov = 0;
        $conge_history->is_archive = 0;
        $conge_history->level = 3;
        $conge_history->conge_id = $id_conge;
        $conge_history->save();


        $conge = Conge::findOrFail($id_conge);
        $conge->status="En cours";
        $conge->save();

        $this->user = User::findOrFail($conge['user_id']);

        $List_conges = [];

        $allConges = Conge::where([['is_deleted', '=', 0],['status','=','En cours'],['level','=',3],['id','=',$id_conge]])->with([
            'histories' => fn($query) => $query->where([['is_rejected_prov', '=', 0],['level', '=', 3],['status','=','Accepter'],['conge_id', '=', $id_conge]]),
        ])->get();

        if(count($allConges) != 0){
            foreach($allConges as $conge) {
                array_push($List_conges,$conge);
            }
        }

        $gerants = CongeController::getAllGerants();

        if(count($List_conges) != 0){
            if(count($List_conges[0]['histories']) == count($gerants) ){
                $conge = DB::table('conges')
                ->select('conges.*')
                ->where([
                    ['id', '=',$id_conge],
                    ['is_deleted', '=', 0],
                    ['level', '=', 3],
                ])->update(['status' => "Accepter"]);

                $this->userPassConge = User::findOrFail($List_conges[0]['user_id']);
                $this->result = "Accepter";

                Mail::send('conge.Acceptation', ['user' => $this->userPassConge, 'dates' => $List_conges[0]['dates']], function($message) {
                    $message->to($this->user['email']);
                    $message->subject('Acceptance of your leave request');
                });
            }
        }

        return $List_conges;
    }

    public function accepterConge($id_conge)
    {
        $result = [];

        $this->user = Auth::user();
        $conge = Conge::findOrFail($id_conge);

        $test_fonction = CongeController::test_Leader_ChefDep_Gerant($this->user['id']);

        if($test_fonction['leader'] == 1 && $test_fonction['chef_dep'] == 0 && $test_fonction['gerant'] == 0){
           $result = CongeController::acceptCongeLeader($id_conge);
        }
        if($test_fonction['leader'] == 0 && $test_fonction['chef_dep'] == 1 && $test_fonction['gerant'] == 0){
            $result = CongeController::acceptCongeChefDep($id_conge);
        }
        if($test_fonction['leader'] == 0 && $test_fonction['chef_dep'] == 0 && $test_fonction['gerant'] == 1){
            $result = CongeController::acceptCongeGerant($id_conge);
        }
        if($test_fonction['leader'] == 1 && $test_fonction['chef_dep'] == 1 && $test_fonction['gerant'] == 0){
            $result_1 = CongeController::acceptCongeLeader($id_conge);
            $result_2 = CongeController::acceptCongeChefDep($id_conge);
            $result = array_merge($result_1, $result_2);
         }

         if($test_fonction['leader'] == 0 && $test_fonction['chef_dep'] == 1 && $test_fonction['gerant'] == 1 ){
                $result_1 = CongeController::acceptCongeChefDep($id_conge);
                $result_2 = CongeController::acceptCongeGerant($id_conge);
                $result = array_merge($result_1, $result_2);
         }

         if($test_fonction['leader'] == 1 && $test_fonction['chef_dep'] == 0 && $test_fonction['gerant'] == 1){
            $result_1 = CongeController::acceptCongeLeader($id_conge);
            $result_2 = CongeController::acceptCongeGerant($id_conge);
            $result = array_merge($result_1, $result_2);
         }

         if($test_fonction['leader'] == 1 && $test_fonction['chef_dep'] == 1 && $test_fonction['gerant'] == 1){
            $result_1 = CongeController::acceptCongeLeader($id_conge);
            $result_2 = CongeController::acceptCongeChefDep($id_conge);
            $result_3 = CongeController::acceptCongeGerant($id_conge);
            $result = array_merge($result_1, $result_2, $result_3);
         }

        return response()->json([
            'conge' => $result,
            'success' => true
        ], 200);
    }

    public static function SendMailDaily(){
        // get list conges !!
        $conges = Conge::where([['is_deleted', '=', 0],['status', '=', 'EnvoyÃ©']])->orWhere([['is_deleted', '=', 0],['status', '=', 'En cours']])->with([
            'histories' => fn($query) => $query->where([['is_rejected_prov', '=', 0],['is_archive', '=', 0]])
        ])->get();

        foreach ($conges as $conge) {
            $conge['List_responsable'] = [];
            $new_date = date('Y-m-d',strtotime('+1 day',strtotime($conge['date'])));
            $date_now = date('Y-m-d',strtotime(Carbon::now()));

            // tester si la date du demande envoyÃ© depasse 24h !!
                if($new_date === $date_now){
                    $id_leaders = CongeController::get_ids_leaders($conge['user_id']);
                    $id_chef_dep = CongeController::get_ids_chef_dep($conge['user_id']);
                    $id_gerants = CongeController::get_ids_gerants();

                    $List_ids_responsable = [];

                    foreach($conge['histories'] as $history) {
                        array_push($List_ids_responsable,$history['id_responsable']);
                    }

                    // tester si un responsable ne repond pas a une demande de conge
                    if($conge['level'] == 1){
                        $list_responsables = array_diff($id_leaders, $List_ids_responsable);
                    }else if($conge['level'] == 2){
                        $list_responsables = array_diff($id_chef_dep, $List_ids_responsable);
                    }else{
                        $list_responsables = array_diff($id_gerants, $List_ids_responsable);
                    }

                    $List_responsable = [];

                    foreach ($list_responsables as $resp) {
                        $responsable = User::findOrFail($resp);
                        $user = CongeController::getUser($conge['user_id']);

                        $mailData = [
                            'lastName' => $user[0]['lastName'],
                            'firstName' => $user[0]['firstName'],
                            'sex' => $user[1],
                            'date' => $user[2],
                        ];
                        Mail::to($responsable->email)->send(new DailyMail($mailData));

                        array_push($List_responsable,$responsable);
                    }
                    $conge['List_responsable'] = $List_responsable;
              }
        }
    }
*/
    
}
