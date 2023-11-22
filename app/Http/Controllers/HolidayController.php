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
        $responsables = array_filter($responsables, fn ($resp) => $resp != $id);
    
        // Étape 3: Récupération de tous les congés de l'utilisateur
        $holidays = Holiday::getAllHolidayUser($id)->toArray();
    
        // Étape 4: Utilisation de array_map pour transformer chaque congé
        $holidays = array_map(function ($conge) use ($responsables) {
            $list = collect($conge['histories'])
                ->reject(fn ($c) => $c['is_rejected_prov'] != 0)
                ->map(function ($c) {
                    // Assignation de valeurs spécifiques aux statuts
                    return $c['status'] == 'Rejet provisoire' ? -1 : ($c['status'] == 'Rejet définitif' ? 'x' : $c['id_responsible']);
                });
    
            // Étape 6: Calcul du reste et ajout des détails au congé
            $rest = count($responsables) - $list->unique()->count();
            $conge['rest'] = $rest;
            $conge['nb_responsable'] = count($responsables);
            $conge['nb_acceptation'] = $list->unique()->values()->all();
    
            return $conge;
        }, $holidays);
    
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

    public static function determineUserRoleStatus($id_user)
    {
        // Récupérer les leaders associés à l'utilisateur
        $leaders = HolidayController::getLeader($id_user);
        $leadersArray = collect($leaders)->toArray();
        $test_leader = in_array($id_user, array_column($leadersArray, 'id')) ? 1 : 0;
    
        // Vérifier si l'utilisateur est chef de département
        $test_chefDep = Department::where('department_chief', '=', $id_user)->exists() ? 1 : 0;
    
        // Récupérer tous les gérants et vérifier si l'utilisateur est parmi eux
        $gerants = HolidayController::getAllGerants();
        $gerantsArray = collect($gerants)->toArray();
        $test_gerant = in_array($id_user, array_column($gerantsArray, 'id')) ? 1 : 0;
    
        return ["leader" => $test_leader, "department_chief" => $test_chefDep, "gerant" => $test_gerant];
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
        // Afficher la liste pour le gérant
        $result_gerants = Holiday::where('level', 3)
            ->with(['histories' => fn ($query) => $query->where(['is_rejected_prov' => 0, 'level' => 3, 'id_responsible' => $id_auth]), 'user'])
            ->get();
    
        $List_holidays_final = array_filter($result_gerants->toArray(), function ($conge) use ($id_auth) {
            // Vérifier si le responsable est impliqué dans l'historique du congé
            $rep_responsable = count($conge['histories']) > 0 && collect($conge['histories'])->contains('id_responsible', $id_auth);
            return !$rep_responsable;
        });
    
        // Réindexer le tableau pour éviter les clés numériques non continues
        return array_values(array_unique($List_holidays_final, SORT_REGULAR));
    }
    
    public function getAllHoliday($id_auth)
    {
        $result = [];
        $test_fonction = HolidayController::determineUserRoleStatus($id_auth);
    
        if ($test_fonction['leader'] == 1) {
            $result = array_merge($result, HolidayController::getAllHolidayLeader($id_auth));
        } else if ($test_fonction['department_chief'] == 1) {
            $result = array_merge($result, HolidayController::getAllHolidayChefDepartment($id_auth));
        } else if ($test_fonction['gerant'] == 1) {
            $result = array_merge($result, HolidayController::getAllHolidayGerant($id_auth));
        }

        return $this->successResponse($result);
    }

    public function getHistoriqueHolidayLeader($id_auth)
    {
        $List_conges_final = [];
    
        $List_team = TeamUser::where([['user_id', '=', $id_auth], ['is_leader', '=', 1]])->get();
        $team_id = $List_team->pluck('team_id')->toArray();
        $team_id = empty($team_id) ? null : $team_id;
    
        $conges = HolidayHistory::getFilteredHolidays($id_auth, $team_id);
    
        $conges->each(function ($conge) use (&$List_conges_final, $id_auth) {
            HolidayHistory::processHolidayHistoriesForLeadership($conge, $List_conges_final, $id_auth);
        });
    
        return array_values(array_unique($List_conges_final));
    }
    
    public function getHistoriqueHolidayChefDepartment($id_auth)
    {
        $List_id_department = Department::where('department_chief', $id_auth)->pluck('id')->toArray();
    
        $conges = HolidayHistory::getFilteredHolidays($id_auth, $List_id_department);
        $user_dep = HolidayHistory::filterUserDepartments($conges, $List_id_department);
        $List_conges_final = HolidayHistory::processHistoriesForChefDepartment($user_dep, $id_auth);
    
        return array_values(array_unique($List_conges_final));
    }

    public function getHistoriqueHolidayGerant($id_auth)
    {
        $result_gerants = HolidayHistory::getFilteredHolidays($id_auth);
        $List_conges_final =HolidayHistory::processHistoriesForGerant($result_gerants, $id_auth);
    
        return array_values(array_unique($List_conges_final));
    }

    public function getHistoriqueHoliday($id_auth)
    {
        $result = [];
        $test_fonction = HolidayController::determineUserRoleStatus($id_auth);

        if ($test_fonction['leader'] == 1) {
            $result = array_merge($result, HolidayController::getHistoriqueHolidayLeader($id_auth));
        } else if ($test_fonction['department_chief'] == 1) {
            $result = array_merge($result, HolidayController::getHistoriqueHolidayChefDepartment($id_auth));
        } else if ($test_fonction['gerant'] == 1) {
            $result = array_merge($result, HolidayController::getHistoriqueHolidayGerant($id_auth));
        }

        return $this->successResponse($result);
    }

    public function initializeHolidayData($id)
    {
        $this->user = HolidayController::getUser($id);
        $this->leader = HolidayController::getLeader($id);
        $this->gerants = HolidayController::getAllGerants($id);
        $this->department_chief = array_values(array_unique(HolidayController::getChiefDepartement($id)));
    }

    public function processGerant($id)
    {
       $this->initializeHolidayData($id);

        // Vérifier si le responsable appartient à un département sans chef
        if (count($this->department_chief) == 0) {
            // Obtenir les IDs des gérants
            $ids_gerants = HolidayController::get_ids_gerants();

            // Vérifier si le responsable est un gérant
            if (in_array($id, $ids_gerants)) {
                $this->saveHolidayHistory($id, 3);
                $gerants = $this->gerants;
                $this->gerants = [];

                // Filtrer les gérants en retirant celui avec l'ID donné
                $gerants = collect($gerants)->filter(function ($g) use ($id) {
                    return $g['id'] != $id;
                })->all();
                
                $this->gerants = $gerants;
            }

            $conge = Holiday::findOrFail($this->conge['id']);

            // Vérifier s'il n'y a plus de gérants à traiter
            if (count($this->gerants) == 0) {
                $conge->status = "Accepter";
                $conge->level = 3;
                $conge->save();
            } else {
                $conge->level = 3;
                $conge->save();

                $this->sendEmails(array_merge($this->gerants) , 'Leave request' , null, 'conge.InfoEmail');
            }
            $this->conge = $conge;
        }
    }

    public function processLeader($id)
    {
        $this->initializeHolidayData($id);
    
        // Vérifier s'il y a des chefs de département associés à la demande de congé.
        if (count($this->department_chief) > 0) {
            $ids_leaders = HolidayController::get_ids_leaders($id);
            
            // Vérifier si le responsable actuel est parmi les leaders associés.
            if (in_array($id, $ids_leaders)) {
                $this->saveHolidayHistory($id , 1) ;
    
                // Filtrer les leaders pour exclure celui en cours de traitement.
                $this->leader = array_filter($this->leader, function ($leader) use ($id) {
                    return $leader['id'] != $id;
                });
            }
    
            $recipients = count($this->leader) == 0 ? $this->department_chief : $this->leader;
    
            $conge = Holiday::findOrFail($this->conge['id']);
            $conge->level = count($this->leader) == 0 ? 2 : 1;
            $conge->save();
    
            $this->sendEmails($recipients , 'Leave request' , null, 'conge.InfoEmail');
            $this->conge = $conge;
        }
    }

    private function saveHolidayHistory($id, $level)
    {
        $conge_history = new HolidayHistory();
        $conge_history->id_responsible = $id;
        $conge_history->status = "Accepter";
        $conge_history->is_rejected_prov = 0;
        $conge_history->is_archive = 0;
        $conge_history->level = $level;
        $conge_history->holiday_id = $this->conge['id'];

        $conge_history->save();
    }

    public function ResponsableAddHoliday($id)
    {
        $this->processGerant($id);
        $this->processLeader($id);
    }

    private function sendEmails($recipients, $subject, $date = null, $view )
    {
        Mail::send($view, ['user' => $this->user, 'dates' => $date], function ($message) use ($recipients, $subject) {
            foreach ($recipients as $recipient) {
                $message->to($recipient['email']);
            }
            $message->subject($subject);
        });
    }

    public function AddHoliday(Request $request, $id)
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

        return $this->successResponse($this->conge);
    }

    public function updateHoliday(Request $request, $id)
    {
        $this->conge = Holiday::findOrFail($id);

        $conges_history = HolidayHistory::where('holiday_id', '=', $id)->update(['is_rejected_prov' => 1, 'is_archive' => 1]);

        $this->conge->type = $request->input('type');
        $this->conge->raison = $request->input('raison');
        $this->conge->dates = $request->input('dates');
        $this->conge->status = "Envoye";
        $this->conge->level = 1;
        $this->conge->save();

        HolidayController::ResponsableAddHoliday($this->conge->user_id);

        return $this->successResponse($this->conge);
    }

    public function deleteHoliday($id)
    {
        $conge = Holiday::findOrFail($id);
        $conge->delete();

        return $this->successResponse($conge);
    }

    public function AnnulerHoliday($id)
    {
        $conge = Holiday::findOrFail($id);
        $conge->status = "Annuler";
        $conge->save();

        $this->user = User::findOrFail($conge->user_id);

        if ($conge->level == 1) {
            $this->list_responsable = HolidayController::getLeader($conge['user_id']);
        } else if ($conge->level == 2) {
            $this->list_responsable = HolidayController::getChiefDepartement($conge['user_id']);
        } else if ($conge->level == 3) {
            $this->list_responsable = HolidayController::getAllGerants();
        }

        if (count($this->list_responsable) != 0) {
            $this->sendCancellationEmail($conge);
        }

        return $this->successResponse($conge);
    }

    private function sendCancellationEmail($conge)
    {
        Mail::send('conge.AnnulerHoliday', ['conge' => $conge, 'user' => $this->user], function ($message) {
            foreach ($this->list_responsable as $resp) {
                $message->to($resp['email']);
            }
            $message->subject('Response following the cancellation of leaving');
        });
    }

    public function RejetDefinitive(Request $request, $id_conge)
    {
        // $responsable = Auth::user();
        $responsable = User::where("id", 1)->first();
        $conge = Holiday::findOrFail($id_conge);
        HolidayHistory::updateHolidayStatus($id_conge, "Rejet definitif", $conge->level);

        $conge_history = HolidayHistory::createHolidayHistory($responsable->id, "Rejet definitif", 0, 0, $conge->level, $id_conge, $request->raison_reject);
        $this->user = HolidayController::getUser($conge['user_id']);

        Mail::send('conge.RejetDefinitive', ['result' => $conge_history->raison_reject, 'conge' => $conge, 'user' =>  $this->user[0]], function ($message) {
            $message->to($this->user[0]['email']);
            $message->subject('Request rejected');
        });

        return $this->successResponse($conge);
    }

    public function RejetProvisoire(Request $request, $id_conge)
    {
        // $responsable = Auth::user();
        $responsable = User::where("id", 1)->first();
        $conge = Holiday::findOrFail($id_conge);
        $this->updateHolidayStatus($id_conge, "Rejet provisoire", $conge->level);

        $conge_history =HolidayHistory::createHolidayHistory($responsable->id, "Rejet provisoire", 0, 0, $conge->level, $id_conge, $request->raison_reject);
        $this->user = User::findOrFail($conge->user_id);

        Mail::send('conge.RejetProvisoire', ['result' => $conge_history->raison_reject, 'conge' => $conge, 'user' =>  $this->user], function ($message) {
            $message->to($this->user['email']);
            $message->subject('Provisionally refusal of your leave request');
        });

        return $this->successResponse($conge);
    }

    public function acceptHolidayLeader($id_conge)
    {
        // $leader = Auth::user();
        $leader = User::where("id", 1)->first();
        $conge = Holiday::findOrFail($id_conge);
        $List_conges = [];

        $ids_leaders = HolidayController::get_ids_leaders($conge['user_id']);
        if (in_array($leader['id'], $ids_leaders)) {

            HolidayHistory::createHolidayHistory( $leader['id'], "Accepter", 0, 0, 1, $id_conge);
            HolidayHistory::updateHolidayStatus($id_conge, "En cours");

            $allHolidays = Holiday::where([['status', '=', 'Envoye'], ['level', '=', '1'], ['id', '=', $id_conge]])->orWhere([['status', '=', 'En cours'], ['level', '=', '1'], ['id', '=', $id_conge]])->with([
                'histories' => fn ($query) => $query->where([['is_rejected_prov', '=', 0], ['level', '=', 1], ['status', '=', 'Accepter'], ['holiday_id', '=', $id_conge]]),
            ])->get()->toArray();

            $List_conges = array_merge($List_conges, $allHolidays);

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

            if (count($List_conges) != 0) {
                if (count($List_conges[0]['histories']) == count($Leaders)) {
                    $now = Carbon::now();
                    $conge = Holiday::where([
                        ['id', '=', $id_conge],
                        ['level', '=', 1],
                    ])->update(['level' => 2, 'date' => $now]);


                    if (count($this->department_chief) != 0) {
                        Mail::send('conge.InfoEmail', ['user' => $this->user], function ($message) {
                            foreach ($this->department_chief as $chef) {
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

    public function acceptHolidayChefDep($id_conge)
    {
        // $this->user = Auth::user();
        $this->user = User::where("id", 1)->first();
        $conge = Holiday::findOrFail($id_conge);

        $List_conges = [];

        $ids_department_chief = HolidayController::get_ids_department_chief($conge['user_id']);

        if (in_array($this->user['id'], $ids_department_chief)) {
            HolidayHistory::createHolidayHistory( $this->user['id'], "Accepter", 0, 0, 2, $id_conge);
          
            $conge->status = "En cours";
            $conge->save();

            $allHolidays = Holiday::where([['status', '=', 'En cours'], ['level', '=', '2'], ['id', '=', $id_conge]])->with([
                'histories' => fn ($query) => $query->where([['is_rejected_prov', '=', 0], ['level', '=', 2], ['status', '=', 'Accepter'], ['holiday_id', '=', $id_conge]]),
            ])->get();

            if (count($allHolidays) != 0) {
                foreach ($allHolidays as $conge) {
                    array_push($List_conges, $conge);
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

            if (count($List_conges) != 0) {
                if (count($List_conges[0]['histories']) == count($department_chief)) {
                    $now = Carbon::now();
                    $conge = Holiday::where([
                        ['id', '=', $id_conge],
                        ['level', '=', 2],
                    ])->update(['level' => 3, 'date' => $now]);

                    if (count($this->gerants) != 0) {
                        Mail::send('conge.InfoEmail', ['user' => $this->user], function ($message) {
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

    public function acceptHolidayGerant($id_conge)
    {
        // $leader = Auth::user();
        $leader = User::where("id", 2)->first();
        HolidayHistory::createHolidayHistory( $leader['id'], "Accepter", 0, 0, 3, $id_conge);
        HolidayHistory::updateHolidayStatus($id_conge, "En cours");

        $conge = Holiday::findOrFail($id_conge);
        $this->user = User::findOrFail($conge['user_id']);
        $List_conges = [];

        $allHolidays = Holiday::where([['status', '=', 'En cours'], ['level', '=', 3], ['id', '=', $id_conge]])->with([
            'histories' => fn ($query) => $query->where([['is_rejected_prov', '=', 0], ['level', '=', 3], ['status', '=', 'Accepter'], ['holiday_id', '=', $id_conge]]),
        ])->get();

        if (count($allHolidays) != 0) {
            foreach ($allHolidays as $conge) {
                array_push($List_conges, $conge);
            }
        }

        $gerants = HolidayController::getAllGerants();

        if (count($List_conges) != 0) {
            if (count($List_conges[0]['histories']) == count($gerants)) {
                $conge = Holiday::where([
                    ['id', '=', $id_conge],
                    ['level', '=', 3],
                ])->update(['status' => "Accepter"]);

               $this->user = User::findOrFail($List_conges[0]['user_id']);

                Mail::send('conge.Acceptation', ['user' =>$this->user, 'dates' => $List_conges[0]['dates']], function ($message) {
                    $message->to($this->user['email']);
                    $message->subject('Acceptance of your leave request');
                });
            }
        }
        return $List_conges;
    }

    public function accepterHoliday($id_conge)
    {
        $this->user  = User::find(2);
        $test_fonction = HolidayController::determineUserRoleStatus($this->user['id']);
        $result = [];

        if ($test_fonction['leader'] == 1) {
            $result = array_merge($result, HolidayController::acceptHolidayLeader($id_conge));
        }

        if ($test_fonction['department_chief'] == 1) {
            $result = array_merge($result, HolidayController::acceptHolidayChefDep($id_conge));
        }

        if ($test_fonction['gerant'] == 1) {
            $result = array_merge($result, HolidayController::acceptHolidayGerant($id_conge));
        }

        return $this->successResponse($result);
    }
}
