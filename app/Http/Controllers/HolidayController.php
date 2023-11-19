<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\User;
use App\Models\TeamUser;
use App\Models\Position;
use App\Models\Department;
use App\Models\HolidayHistory;
use Carbon\Carbon;
use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class HolidayController extends Controller
{

    private $user = null;
    private $conge = null;

    private $leader = null;
    private $department_chief = [];
    private $gerants = [];

    private $date_actuelle = null;
    private $result = null;
    private $list_responsable = [];

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
        
        return [$user, $sex, Carbon::now()->year];
    }

    public static function getLeader($id_user)
    {
        $teamIds = TeamUser::where('user_id', $id_user)->pluck('team_id');
    
        if($teamIds->isEmpty()){
            return [];
        }
    
        $teamLeaders = TeamUser::whereIn('team_id', $teamIds)
            ->where('is_leader',1)
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
        ])->with(['users.user' => fn($query) => $query->where('status', '=', 'active')])->get();
    
        $gerants = $gerantUsers->flatMap(function ($position) {
            return $position['users']->pluck('user');
        });
    
        return $gerants->unique()->values();
    }

    public static function get_ids_leaders($id)
    {
        $leader = HolidayController::getLeader($id);
        return array_column($leader, 'id');
    }

    public static function get_ids_department_chief($id)
    {
        $department_chief = HolidayController::getChiefDepartement($id);
        return array_column($department_chief, 'id');
    }

    public static function get_ids_gerants()
    {
        $gerant = HolidayController::getAllGerants();
        $gerantArray = $gerant->toArray();
        return array_column($gerantArray, 'id');
    }
    
    public function getHolidayUser($id)
    {
        // Étape 1: Récupération des responsables de l'utilisateur spécifié
        $responsables = array_values(array_unique(array_merge(
            HolidayController::get_ids_leaders($id),
            HolidayController::get_ids_department_chief($id),
            HolidayController::get_ids_gerants()
        )));
    
        // Étape 2: Filtrage pour exclure l'utilisateur actuel des responsables
        $responsables = array_filter($responsables, fn($resp) => $resp != $id);
    
        // Étape 3: Récupération de tous les congés de l'utilisateur
        $holidays = Holiday::getAllHolidayUser($id);
    
        // Étape 4: Traitement des détails pour chaque congé
        foreach ($holidays as $conge) {
            $list = collect($conge['histories'])
                ->reject(fn($c) => $c['is_rejected_prov'] != 0)
                ->map(function ($c) {
                    // Assignation de valeurs spécifiques aux statuts
                    return $c['status'] == 'Rejet provisoire' ? -1 : ($c['status'] == 'Rejet définitif' ? 'x' : $c['id_responsible']);
                });
    
            // Étape 6: Calcul du reste et ajout des détails au congé
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

        return $this->successResponse( $result );
    }

    public function ResponsableAddHoliday($id)
    {
        $this->user = HolidayController::getUser($id);

        $this->leader = HolidayController::getLeader($id);

        $this->gerants = HolidayController::getAllGerants($id);

        $this->department_chief = array_values(array_unique(HolidayController::getChiefDepartement($id)));

        if(count($this->department_chief) == 0){

            $ids_gerants = HolidayController::get_ids_gerants();
            if(in_array($id,$ids_gerants)){
                $conge_history = new HolidayHistory();
                $conge_history->id_responsible = $id;
                $conge_history->status = "Accepter";
                $conge_history->is_rejected_prov = 0;
                $conge_history->is_archive = 0;
                $conge_history->level = 3;
                $conge_history->holiday_id = $this->conge['id'];
                $conge_history->save();

                $gerants = $this->gerants;
                $this->gerants = [];
                foreach($gerants as $g) {
                    if($g['id'] != $id){
                      array_push($this->gerants,$g);
                    }
                }
            }
                $conge = Holiday::findOrFail($this->conge['id']);

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
                $ids_leaders = HolidayController::get_ids_leaders($id);

                if(in_array($id,$ids_leaders)){
                    $conge_history = new HolidayHistory();
                    $conge_history->id_responsible = $id;
                    $conge_history->status = "Accepter";
                    $conge_history->is_rejected_prov = 0;
                    $conge_history->is_archive = 0;
                    $conge_history->level = 1;
                    $conge_history->holiday_id = $this->conge['id'];
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
                    $conge = Holiday::findOrFail($this->conge['id']);
                    $conge->level = 2;
                    $conge->save();

                    Mail::send('conge.InfoEmail', ['user' => $this->user], function($message) {
                        foreach ($this->department_chief as $department_chief) {
                           $message->to($department_chief['email']);
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

    public function AddHoliday(Request $request,$id)
    {
        $this->conge = new Holiday();
        $this->conge->type = $request->input('type');
        $this->conge->raison = $request->input('raison');
        $this->conge->dates = $request->input('dates');
        $this->conge->level = 1;
        $this->conge->date = Carbon::now();
        $this->conge->status = "Envoye";
        $this->conge->user_id = $id;
        $this->conge->save();

        HolidayController::ResponsableAddHoliday($id);

        return $this->successResponse( $this->conge);
    }

    
    public function updateHoliday(Request $request,$id)
    {
        $this->conge = Holiday::findOrFail($id);

        $conges_history = HolidayHistory::where('holiday_id','=',$id)->update(['is_rejected_prov' =>1,'is_archive'=> 1]);

        $this->conge->type = $request->input('type');
        $this->conge->raison = $request->input('raison');
        $this->conge->dates = $request->input('dates');
        $this->conge->status = "Envoye";
        $this->conge->level = 1;
        $this->conge->save();

        HolidayController::ResponsableAddHoliday($this->conge->user_id);

        return $this->successResponse( $this->conge);
    }

    
    public function deleteHoliday($id)
    {
        $conge = Holiday::findOrFail($id);
        $conge->is_deleted = 1;
        $conge->save();

        return $this->successResponse( $conge);
    }

    public function AnnulerHoliday($id)
    {
        $conge = Holiday::findOrFail($id);
        $conge->status = "Annuler";
        $conge->save();

        $this->result = "demande annuler";

        $this->user = User::findOrFail($conge->user_id);

        if($conge->level == 1){
            $this->list_responsable = HolidayController::getLeader($conge['user_id']);
        }else if($conge->level == 2){
            $this->list_responsable = HolidayController::getChiefDepartement($conge['user_id']);
        }else if ($conge->level == 3){
            $this->list_responsable = HolidayController::getAllGerants();
        }

        if(count($this->list_responsable) != 0){
            Mail::send('conge.AnnulerHoliday', ['conge' => $conge, 'user' =>  $this->user], function($message) {
                foreach ($this->list_responsable as $resp) {
                    $message->to($resp['email']);
                }
                $message->subject('Response following the cancellation of leaving');
            });
        }

        return $this->successResponse( $conge);
    }

    public function RejetDefinitive(Request $request,$id_conge)
    {
        $responsable = Auth::user();
        // $responsable = User::where("id",1)->first();
        $conge = Holiday::findOrFail($id_conge);
        $conge->status = "Rejet definitif";
        $conge->save();

        $conge_history = new HolidayHistory();
        $conge_history->id_responsible = $responsable['id'];
        $conge_history->status = "Rejet definitif";
        $conge_history->is_rejected_prov = 0;
        $conge_history->is_archive = 0;
        $conge_history->raison_reject = $request->raison_reject;
        $conge_history->level = $conge->level;
        $conge_history->holiday_id = $id_conge;
        $conge_history->save();

        $this->user = HolidayController::getUser($conge['user_id']);

        $this->result = "Rejet definitive";

        Mail::send('conge.RejetDefinitive', ['result' => $conge_history->raison_reject, 'conge'=> $conge, 'user' =>  $this->user[0]], function($message) {
            $message->to($this->user[0]['email']);
            $message->subject('Request rejected');
        });

        return $this->successResponse( $conge);
    }

    public function RejetProvisoire(Request $request,$id_conge)
    {
        $responsable = Auth::user();
        // $responsable = User::where("id",1)->first();
        $conge = Holiday::findOrFail($id_conge);
        $conge->status = "Rejet provisoire";
        $conge->save();

        $conge_history = new HolidayHistory();
        $conge_history->id_responsible = $responsable['id'];
        $conge_history->status = "Rejet provisoire";
        $conge_history->is_archive = 0;
        $conge_history->is_rejected_prov = 0;
        $conge_history->raison_reject = $request->raison_reject;
        $conge_history->level = $conge->level;
        $conge_history->holiday_id = $id_conge;
        $conge_history->save();

        $this->user = User::findOrFail($conge->user_id);

        $this->result = "Rejet provisoire";

        Mail::send('conge.RejetProvisoire', ['result' => $conge_history->raison_reject, 'conge'=> $conge, 'user' =>  $this->user], function($message) {
            $message->to($this->user['email']);
            $message->subject('Provisionally refusal of your leave request');
        });

        return $this->successResponse( $conge);
    }

    public function acceptHolidayLeader($id_conge)
    {
        // $leader = Auth::user();
        $leader = User::where("id",1)->first();

        $List_conges = [];

        $conge = Holiday::findOrFail($id_conge);

        $ids_leaders = HolidayController::get_ids_leaders($conge['user_id']);
        if(in_array($leader['id'],$ids_leaders)){
            $conge_history = new HolidayHistory();
            $conge_history->id_responsible = $leader['id'];
            $conge_history->status = "Accepter";
            $conge_history->is_rejected_prov = 0;
            $conge_history->is_archive = 0;
            $conge_history->level = 1;
            $conge_history->holiday_id = $id_conge;
            $conge_history->save();

            $conge->status="En cours";
            $conge->save();

            $allHolidays = Holiday::where([['status','=','Envoye'],['level','=','1'],['id','=',$id_conge]])->orWhere([['status','=','En cours'],['level','=','1'],['id','=',$id_conge]])->with([
                'histories' => fn($query) => $query->where([['is_rejected_prov', '=', 0],['level', '=', 1],['status','=','Accepter'],['holiday_id', '=', $id_conge]]),
            ])->get();

            if(count($allHolidays) != 0){
                foreach($allHolidays as $conge) {
                    array_push($List_conges,$conge);
                }
            }
            $Leaders = HolidayController::getLeader($conge['user_id']);
            $department_chief = HolidayController::getChiefDepartement($conge['user_id']);
            $this->user = HolidayController::getUser($conge['user_id']);
            
            $LeadersArray = is_array($Leaders) ? $Leaders : [$Leaders];
            $departmentChiefArray = is_array($department_chief) ? $department_chief : [$department_chief];
        
            $result = [];

            foreach ($LeadersArray as $item) {
                if (isset($item['id'])) {
                    $result[$item['id']] = $item;
                }
            }
            
            foreach ($departmentChiefArray as $item) {
                if (isset($item['id'])) {
                    $result[$item['id']] = $item;
                }
            }

            $this->department_chief = array_values($result);

            if(count($List_conges) != 0){
                if(count($List_conges[0]['histories']) == count($Leaders) ){
                    $now = Carbon::now();
                    $conge = Holiday::where([
                        ['id', '=',$id_conge],
                        ['level', '=', 1],
                    ])->update(['level' => 2,'date' => $now]);

                  
                        if(count($this->department_chief) != 0){
                            Mail::send('conge.InfoEmail', ['user' => $this->user], function($message) {
                                foreach ($this->department_chief as $chef) {
                                    $message->to($chef['email']);
                                }
                                $message->subject('Leave request');
                            });
                        }
                }
            }
        }

        return $this->successResponse( $List_conges);
    }

   public function acceptHolidayChefDep($id_conge)
    {
        // $this->user = Auth::user();
        $this->user = User::where("id",1)->first();

        $conge = Holiday::findOrFail($id_conge);

        $List_conges = [];

        $ids_department_chief = HolidayController::get_ids_department_chief($conge['user_id']);

        if(in_array($this->user['id'],$ids_department_chief)){
            $conge_history = new HolidayHistory();
            $conge_history->id_responsible = $this->user['id'];
            $conge_history->status = "Accepter";
            $conge_history->is_rejected_prov = 0;
            $conge_history->is_archive = 0;
            $conge_history->level = 2;
            $conge_history->holiday_id = $id_conge;
            $conge_history->save();

            $conge->status="En cours";
            $conge->save();

            $allHolidays = Holiday::where([['status','=','En cours'],['level','=','2'],['id','=',$id_conge]])->with([
                'histories' => fn($query) => $query->where([['is_rejected_prov', '=', 0],['level', '=', 2],['status','=','Accepter'],['holiday_id', '=', $id_conge]]),
            ])->get();

            if(count($allHolidays) != 0){
                foreach($allHolidays as $conge) {
                    array_push($List_conges,$conge);
                }
            }

            $department_chief = HolidayController::getChiefDepartement($conge['user_id']);
            $gerants = HolidayController::getAllGerants();
            $this->user = HolidayController::getUser($conge['user_id']);
            
            // Convertir en tableau si nécessaire
            $departmentChiefArray = is_array($department_chief) ? $department_chief : [$department_chief];
            $gerantsArray = is_array($gerants) ? $gerants : [$gerants];
            
            // Fusionner les tableaux sans doublons en fonction de l'identifiant
            $result = [];
            
            foreach ($gerantsArray as $item) {
                if (isset($item['id'])) {
                    $result[$item['id']] = $item;
                }
            }
            
            foreach ($departmentChiefArray as $item) {
                if (isset($item['id'])) {
                    $result[$item['id']] = $item;
                }
            }
            
            $this->gerants = array_values($result);

            if(count($List_conges) != 0){
                if(count($List_conges[0]['histories']) == count($department_chief)){
                    $now = Carbon::now();
                    $conge = Holiday::where([
                        ['id', '=',$id_conge],
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
            return $this->successResponse( $List_conges);
    }
 
    public function acceptHolidayGerant($id_conge)
    {
        // $leader = Auth::user();
        $leader = User::where("id",1)->first();

        $conge_history = new HolidayHistory();
        $conge_history->id_responsible = $leader['id'];
        $conge_history->status = "Accepter";
        $conge_history->is_rejected_prov = 0;
        $conge_history->is_archive = 0;
        $conge_history->level = 3;
        $conge_history->holiday_id = $id_conge;
        $conge_history->save();


        $conge = Holiday::findOrFail($id_conge);
        $conge->status="En cours";
        $conge->save();

        $this->user = User::findOrFail($conge['user_id']);

        $List_conges = [];

        $allHolidays = Holiday::where([['status','=','En cours'],['level','=',3],['id','=',$id_conge]])->with([
            'histories' => fn($query) => $query->where([['is_rejected_prov', '=', 0],['level', '=', 3],['status','=','Accepter'],['holiday_id', '=', $id_conge]]),
        ])->get();

        if(count($allHolidays) != 0){
            foreach($allHolidays as $conge) {
                array_push($List_conges,$conge);
            }
        }

        $gerants = HolidayController::getAllGerants();

        if(count($List_conges) != 0){
            if(count($List_conges[0]['histories']) == count($gerants) ){
                $conge = Holiday::where([
                    ['id', '=',$id_conge],
                    ['level', '=', 3],
                ])->update(['status' => "Accepter"]);

                $this->userPassHoliday = User::findOrFail($List_conges[0]['user_id']);
                $this->result = "Accepter";

                Mail::send('conge.Acceptation', ['user' => $this->userPassHoliday, 'dates' => $List_conges[0]['dates']], function($message) {
                    $message->to($this->user['email']);
                    $message->subject('Acceptance of your leave request');
                });
            }
        }
        return $this->successResponse( $List_conges);
    }
 
    public function accepterHoliday($id_conge)
    {
        $result = [];
        // $this->user = Auth::user();
        $this->user  = User::where("id",1)->first();

        $conge = Holiday::findOrFail($id_conge);

        $test_fonction = HolidayController::test_Leader_ChefDep_Gerant($this->user['id']);

        if($test_fonction['leader'] == 1 && $test_fonction['department_chief'] == 0 && $test_fonction['gerant'] == 0){
           $result = HolidayController::acceptHolidayLeader($id_conge);
        }
        if($test_fonction['leader'] == 0 && $test_fonction['department_chief'] == 1 && $test_fonction['gerant'] == 0){
            $result = HolidayController::acceptHolidayChefDep($id_conge);
        }
        if($test_fonction['leader'] == 0 && $test_fonction['department_chief'] == 0 && $test_fonction['gerant'] == 1){
            $result = HolidayController::acceptHolidayGerant($id_conge);
        }
        if($test_fonction['leader'] == 1 && $test_fonction['department_chief'] == 1 && $test_fonction['gerant'] == 0){
            $result_1 = HolidayController::acceptHolidayLeader($id_conge);
            $result_2 = HolidayController::acceptHolidayChefDep($id_conge);
            $result = array_merge($result_1, $result_2);
         }

         if($test_fonction['leader'] == 0 && $test_fonction['department_chief'] == 1 && $test_fonction['gerant'] == 1 ){
                $result_1 = HolidayController::acceptHolidayChefDep($id_conge);
                $result_2 = HolidayController::acceptHolidayGerant($id_conge);
                $result = array_merge($result_1, $result_2);
         }

         if($test_fonction['leader'] == 1 && $test_fonction['department_chief'] == 0 && $test_fonction['gerant'] == 1){
            $result_1 = HolidayController::acceptHolidayLeader($id_conge);
            $result_2 = HolidayController::acceptHolidayGerant($id_conge);
            $result = array_merge($result_1, $result_2);
         }

         if($test_fonction['leader'] == 1 && $test_fonction['department_chief'] == 1 && $test_fonction['gerant'] == 1){
            $result_1 = HolidayController::acceptHolidayLeader($id_conge);
            $result_2 = HolidayController::acceptHolidayChefDep($id_conge);
            $result_3 = HolidayController::acceptHolidayGerant($id_conge);
            $result = array_merge($result_1, $result_2, $result_3);
         }

         return $this->successResponse( $result);
    }
    
}
