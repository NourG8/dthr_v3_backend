<?php

namespace App\Http\Controllers;

use App\Models\Telework;
use App\Models\Department;
use App\Http\Requests\Telework\TeleworkRequest;
use App\Http\Requests\Telework\RejetProvisoireRequest;
use App\Models\TeamUser;
use App\Models\User;
use App\Models\teleworkHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;


class TeleworkController extends Controller
{
    private $tel = null;
    private $user = null;
    private $email = null;
    private $date_actuelle = null;
    private $url = null;
    private $userPassTelework = null;

    private $leader = null;
    private $department_chief = null;
    private $gerants = null;
    private $telework = null;
    private $list_responsable = [];
    private $result = null;

    public function getAllTeleworks($id_auth)
    {
        $result = [];
        // ken user auth = leader !!
        $test_fonction = HolidayController::determineUserRoleStatus($id_auth);

        if ($test_fonction['leader'] == 1) {
            $result = array_merge($result, TeleworkController::getAllTeleworkLeader($id_auth));
        } else if ($test_fonction['department_chief'] == 1) {
            $result = array_merge($result, TeleworkController::getAllTeleworkChefDepartment($id_auth));
        } else if ($test_fonction['gerant'] == 1) {
            $result = array_merge($result, TeleworkController::getAllTeleworkGerant($id_auth));
        }

        return $this->successResponse($result);
    }

    public function initializeHolidayData($id)
    {
        $this->user = HolidayController::getUser($id);
        $this->leader = User::getLeader($id);
        $this->gerants = User::getAllGerants($id);
        $this->department_chief = array_values(array_unique(User::getChiefDepartement($id)));
    }
    
    private function saveHolidayHistory($id, $level)
    {
        $tel_history = new teleworkHistory();
        $tel_history->id_responsible = $id;
        $tel_history->status = "Accepter";
        $tel_history->is_rejected_prov = 0;
        $tel_history->level = $level;
        $tel_history->is_archive = 0;
        $tel_history->telework_id = $this->telework['id'];

        $tel_history->save();
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

    public function processGerant($id)
    {
        $this->initializeHolidayData($id);
        
        if (count($this->department_chief) == 0) {
            $ids_gerants = User::get_ids_gerants($id);

            if (in_array($id, $ids_gerants)) {
                $this->saveHolidayHistory($id , 3) ;

                $gerants = $this->gerants;
                $this->gerants = [];

                $gerants = collect($gerants)->filter(function ($g) use ($id) {
                    return $g['id'] != $id;
                })->all();

                $this->gerants = $gerants;
            }

            $tele = Telework::findOrFail($this->telework['id']);

            if (count($this->gerants) == 0) {
                $tele->status = "Accepter";
                $tele->level = 3;
                $tele->save();
            } else {
                $tele->level = 3;
                $tele->save();

                $this->sendEmails(array_merge($this->gerants) , 'Remote work request' , null, 'telework.InfoEmail');
            }
            $this->telework = $tele;
        }
    }

    public function processLeader($id)
    {
       $this->initializeHolidayData($id);

        // Vérifier si le responsable appartient à un département sans chef
        if (count($this->department_chief) > 0) {
            $ids_leaders = User::get_ids_leaders($id);

            if (in_array($id, $ids_leaders)) {
                $this->saveHolidayHistory($id , 1) ;
                $this->leader = array_filter($this->leader, function ($leader) use ($id) {
                    return $leader['id'] != $id;
                });
            }

            $recipients = count($this->leader) == 0 ? $this->department_chief : $this->leader;
    
            $tele = Telework::findOrFail($this->telework['id']);
            $tele->level = count($this->leader) == 0 ? 2 : 1;
            $tele->save();

            $this->sendEmails($recipients , 'Remote work request' , null, 'telework.InfoEmail');

            $this->telework = $tele;
        }
    }

    public function ResponsableAddTelework($id)
    {
        $this->processGerant($id);
        $this->processLeader($id);  
    }

    public function AddTelework(TeleworkRequest $request)
    {
        // $user = Auth::user();
        $user = User::where('id', 6)->first();
        $validatedData = $request->validated();
        
        $this->telework = Telework::create([
            'raison' => $validatedData ['raison'],
            'date' =>   $validatedData ['date'],
            'level' => 1,
            'status' => "Envoyé",
            'user_id' => $user->id,
        ]);

        TeleworkController::ResponsableAddTelework($user->id);

        return $this->successResponse($this->telework);
    }

    public function getTeleworksUser()
    {
        // $this->user = Auth::user();
        $this->user = User::where('id', 1)->first();

        $teleworks = Telework::where([
            ['teleworks.user_id', '=', $this->user->id],
        ])->with([
            'user' => fn ($query) => $query->where([['status', '=', "active"]])
        ])->get();

        return $this->successResponse($teleworks);
    }

    public function editTelework(TeleworkRequest $request, $id)
    {
        $validatedData = $request->validated();

        $this->telework = Telework::findOrFail($id);
        // $tels_history = teleworkHistory::where('telework_id','=',$id)->update(['is_rejected_prov' =>1,'is_archive'=> 1]);

        $this->telework->update([
            'raison' => $validatedData['raison'],
            'date' =>   $validatedData['date'],
            'level' => 1,
            'status' => "Envoyé"
        ]);

        TeleworkController::ResponsableAddTelework($this->telework->user_id);

        return $this->successResponse($this->telework);
    }

    public function destroyTelework($id)
    {
        $telework = Telework::findOrFail($id);
        $telework->delete();

        return $this->successResponse($telework);
    }

    //nbre de leader pour chaque user
    public function getNbLeaders($id)
    {
        $leaders = User::getLeader($id);
        $nbLeaders = [];
        foreach ($leaders as $leader) {
            if ($leader['id'] != $id) {
                array_push($nbLeaders, $leader);
            }
        }
        return count($nbLeaders);
    }

    public function getNbChefDep($id)
    {
        $chefDep = User::getChiefDepartement($id);
        $nbChefDep = [];
        foreach ($chefDep as $chef) {
            if ($chef['id'] != $id) {
                array_push($nbChefDep, $chef);
            }
        }
        return count($nbChefDep);
    }

    public function getNbGerants()
    {
        $gerants = User::getAllGerants();
        return count($gerants);
    }

    public function acceptTelChefDep($id)
    {
        $List_teltravail = [];
        // $this->user = Auth::user();  
        $this->user = User::where('id', 1)->first();

        $telework = Telework::findOrFail($id);
        $ids_department_chief = User::get_ids_department_chief($telework['user_id']);

        if (in_array($this->user['id'], $ids_department_chief)) {
            teleworkHistory::create([
                'is_rejected_prov' => 0,
                'level' => 2,
                'is_archive' => 0,
                'status' => 'Accepter',
                'telework_id' => $id,
                'id_responsible' => $this->user->id,
            ]);
            $telework->update(['status' => 'En cours']);

            $teleworks = Telework::where([['status', '=', 'Envoyé'], ['level', '=', '2'], ['id', '=', $id]])->orWhere([['status', '=', 'En cours'], ['level', '=', '2'], ['id', '=', $id]])
                ->with([
                    'histories' => fn ($query) => $query->where([['is_rejected_prov', '=', 0], ['level', '=', 2], ['status', '=', 'Accepter'], ['telework_id', '=', $id]]),
                ])->get();

            if (count($teleworks) > 0) {
                foreach ($teleworks as $telework) {
                    array_push($List_teltravail, $telework);
                }
            }
            $department_chief = User::getChiefDepartement($telework['user_id']);
            $gerants = User::getAllGerants();
            $this->user = HolidayController::getUser($telework['user_id']);

            $gerantsArray = is_array($gerants) ? $gerants : [$gerants];
            $departmentChiefArray = is_array($department_chief) ? $department_chief : [$department_chief];

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

            if (count($List_teltravail) > 0) {
                if (count($List_teltravail[0]['histories']) == count($department_chief)) {
                    $teleworks = Telework::where([
                        ['id', '=', $id],
                        ['level', '=', 2],
                    ])->update(['level' => 3]);

                    if (count($this->gerants) != 0) {
                        Mail::send('telework.InfoEmail', ['user' => $this->user], function ($message) {
                            foreach ($this->gerants as $gerant) {
                                $message->to($gerant['email']);
                            }
                            $message->subject('Leave request');
                        });
                    }
                }
            }
        }

        return $List_teltravail;
    }

    public function acceptTelGerant($id)
    {
        // $leader = Auth::user();
        $leader = User::where('id', 1)->first();

        teleworkHistory::create([
            'is_rejected_prov' => 0,
            'level' => 3,
            'is_archive' => 0,
            'status' => 'Accepter',
            'telework_id' => $id,
            'id_responsible' => $leader->id,
        ]);
        $telework = Telework::findOrFail($id);
        $telework->update(['status' => 'En cours']);

        $this->user = User::findOrFail($telework['user_id']);
        $List_teltravail = [];

        $teleworks = Telework::where([['status', '=', 'En cours'], ['level', '=', '3'], ['id', '=', $id]])
            ->with([
                'histories' => fn ($query) => $query->where([['is_rejected_prov', '=', 0], ['level', '=', 3], ['status', '=', 'Accepter'], ['telework_id', '=', $id]]),
            ])->get();

        if (count($teleworks) != 0) {
            foreach ($teleworks as $telework) {
                array_push($List_teltravail, $telework);
            }
        }

        $gerants = User::getAllGerants();
        if (count($List_teltravail) != 0) {
            if (count($List_teltravail[0]['histories']) == count($gerants)) {
                $telework = Telework::where([
                    ['id', '=', $id],
                    ['level', '=', 3],
                ])->update(['status' => "Accepter"]);

                $this->userPassTelework = User::findOrFail($List_teltravail[0]['user_id']);
                $this->result = "Accepter";
                $datesString = $List_teltravail[0]['date'];
                $dates = json_decode($datesString);
                // return $dates;

                Mail::send('telework.responseAccepte', ['user' => $this->userPassTelework, 'dates' => $dates], function ($message) {
                    //   $this->user = Auth::user();
                    $this->user = User::where('id', 1)->first();
                    $message->from($this->user['email']);
                    $message->to($this->userPassTelework['email']);
                    $message->subject('Acceptance of your remote work request');
                });
            }
        }

        return $List_teltravail;
    }

    public function acceptTelLeader($id_telework)
    {
        // $leader = Auth::user();
        $leader = User::where('id', 1)->first();

        $List_teleworks = [];
        $telework = Telework::findOrFail($id_telework);
        $ids_leaders = User::get_ids_leaders($telework['user_id']);

        if (in_array($leader['id'], $ids_leaders)) {
            teleworkHistory::create([
                'is_rejected_prov' => 0,
                'level' => 1,
                'status' => 'Accepter',
                'is_archive' => 0,
                'telework_id' => $id_telework,
                'id_responsible' => $leader->id,
            ]);
        
            $telework->update(['status' => 'En cours']);

            $teleworks = Telework::where([['status', '=', 'Envoyé'], ['level', '=', '1'], ['id', '=', $id_telework]])->orWhere([['status', '=', 'En cours'], ['level', '=', '1'], ['id', '=', $id_telework]])->with([
                'histories' => fn ($query) => $query->where([['is_rejected_prov', '=', 0], ['level', '=', 1], ['status', '=', 'Accepter'], ['telework_id', '=', $id_telework]]),
            ])->get();

            if (count($teleworks) != 0) {
                foreach ($teleworks as $telework) {
                    array_push($List_teleworks, $telework);
                }
            }

            $Leaders = User::getLeader($telework['user_id']);
            $department_chief = User::getChiefDepartement($telework['user_id']);
            $this->user = HolidayController::getUser($telework['user_id']);
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

            if (count($List_teleworks) != 0) {
                if (count($List_teleworks[0]['histories']) == count($Leaders)) {
                    $telework = Telework::where([
                        ['id', '=', $id_telework],
                        ['level', '=', 1],
                    ])->update(['level' => 2]);

                    if (count($this->department_chief) != 0) {
                        Mail::send('telework.InfoEmail', ['user' => $this->user], function ($message) {
                            foreach ($this->department_chief as $chef) {
                                $message->to($chef['email']);
                            }
                            $message->subject('Leave request');
                        });
                    }
                }
            }
        }
        return $List_teleworks;
    }

    public function accepter($id)
    {
        $result = [];
        // $this->user = Auth::user();
        $this->user = User::where('id', 1)->first();
        $test_fonction = HolidayController::determineUserRoleStatus($this->user['id']);

        if ($test_fonction['leader'] == 1) {
            $result = array_merge($result, TeleworkController::acceptTelLeader($id));
        }

        if ($test_fonction['department_chief'] == 1) {
            $result = array_merge($result, TeleworkController::acceptTelChefDep($id));
        }

        if ($test_fonction['gerant'] == 1) {
            $result = array_merge($result, TeleworkController::acceptTelGerant($id));
        }

        return $this->successResponse( $result);
    }

    public function refuseTeletravail(Request $request, $id)
    {
        $telework = Telework::findOrFail($id);
        $telework->update(['status' => "Refusé"]);

        $user = User::findOrFail($telework->user_id);
        $email = $user->email;

        Mail::send('email.responseRefuse', ['user' => $user, 'email' => $email], function ($message) use ($email) {
            $userSender = User::where('id', 1)->first();
            $message->from($userSender->email);
            $message->to($email);
            $message->subject('Refusal of your remote work request');
        });

        return $this->successResponse($telework);
    }

    public function getAllTeleworkLeader($id_auth)
    {
        // TeleworkControlle::getNbLeaders()
        $List_teleworks = [];
        $team_id = [];

        $team_id = TeamUser::where('user_id', $id_auth)
        ->where('is_leader', 1)
        ->pluck('team_id')
        ->toArray();

        // $user = Auth::user();
        $teleworks = Telework::where([['level', '=', '1'], ['status', '=', 'En cours']])
            ->orwhere([['level', '=', '1'], ['status', '=', 'Envoyé']])
            ->with(['histories'])->whereHas('histories')->with(['user'])
            ->get();

        $user_team = [];
        foreach ($teleworks as $telework) {
            foreach ($telework['user']['teams'] as $team) {
                if ($team_id != null) {
                    if (in_array($team['team_id'], $team_id)) {
                        array_push($List_teleworks, $telework);
                        $user_team = array_values(array_unique($List_teleworks));
                    }
                }
            }
        }

        $rep_responsable = false;
        $List_teleworks_final = [];
        foreach ($user_team as $telework) {
            if (count($telework['histories']) == 0) {
                array_push($List_teleworks_final, $telework);
            } else {
                foreach ($telework['histories'] as $history) {
                    if ($history['id_responsible'] == $id_auth) {
                        $rep_responsable = true;
                    } else {
                        array_push($List_teleworks_final, $telework);
                    }
                }
            }
        }
        return $result = array_values(array_unique($List_teleworks_final));
    }

    public function getAllTeleworkChefDepartment($id_auth)
    {
        $List_teleworks = [];
        $List_department = Department::where([['department_chief', '=', $id_auth]])->get();
        $List_id_department = [];

        // return id team eli appartient liha el id_auth kenou leader ala equipe !!!
        if (count($List_department) != 0) {
            foreach ($List_department as $dep) {
                array_push($List_id_department, $dep['id']);
            }
        } else {
            $List_id_department =  null;
        }

        $teleworks = Telework::where([['level', '=', 2], ['status', '!=', 'Annuler']])->with([
            'histories' => fn ($query) => $query->where([['is_rejected_prov', '=', 0], ['level', '=', 2], ['id_responsible', '=', $id_auth]]),
            'user' => fn ($query) => $query->with([
                'teams' => ([
                    'team' => fn ($query) => $query->where([['status', '=', 'active']])->with([
                        'department' => fn ($query) => $query->where([['status', '=', 'active']])->pluck('id')
                    ])
                ])
            ])
        ])->get();

        $user_dep = [];

        foreach ($teleworks as $telework) {
            foreach ($telework['user']['teams'] as $team) {
                if ($team['team'] != null && $List_id_department != []) {
                    if (in_array($team['team']['department']['id'], $List_id_department)) {
                        array_push($List_teleworks, $telework);
                        $user_dep = array_values(array_unique($List_teleworks));
                    }
                }
            }
        }
        $rep_responsable = false;
        $List_teleworks_final = [];

        foreach ($user_dep as $telework) {
            if (count($telework['histories']) == 0) {
                array_push($List_teleworks_final, $telework);
            } else {
                foreach ($telework['histories'] as $history) {
                    if ($history['id_responsible'] == $id_auth) {
                        $rep_responsable = true;
                    } else {
                        array_push($List_teleworks_final, $telework);
                    }
                }
            }
        }
        return $result = array_values(array_unique($List_teleworks_final));
    }

    public function getAllTeleworkGerant($id_auth)
    {
        // $user = Auth::user();
        $user = User::where('id',$id_auth)->first();

        // afficher list pour le gerant !!!!
        $result_gerants = Telework::where([['level', '=', 3], ['status', '!=', 'Annuler']])->with([
            'histories' => fn ($query) => $query->where([['is_rejected_prov', '=', 0], ['level', '=', 3], ['id_responsible', '=', $user->id]]),
            'user' => fn ($query) => $query
        ])->get();

        $rep_responsable = false;

        $List_teleworks_final = [];

        foreach ($result_gerants as $telework) {
            if (count($telework['histories']) == 0) {
                array_push($List_teleworks_final, $telework);
            } else {
                foreach ($telework['histories'] as $history) {
                    if ($history['id_responsible'] == $id_auth) {
                        $rep_responsable = true;
                    } else {
                        array_push($List_teleworks_final, $telework);
                    }
                }
            }
        }
        return $result = array_values(array_unique($List_teleworks_final));
    }

    public function deleteTelework($id)
    {
        $telework = Telework::findOrFail($id);
        $telework->delete();

        return $this->successResponse($telework);
    }

    public function AnnulerTelework($id)
    {
        $telework = Telework::findOrFail($id);
        $telework->status = "Annuler";
        $telework->save();

        $this->user = User::findOrFail($telework->user_id);

        if ($telework->level == 1) {
            $this->list_responsable = User::getLeader($telework['user_id']);
        } else if ($telework->level == 2) {
            $this->list_responsable = User::getChiefDepartement($telework['user_id']);
        } else if ($telework->level == 3) {
            $this->list_responsable = User::getAllGerants();
        }

        if (count($this->list_responsable) != 0) {
            Mail::send('telework.AnnulerTelework', ['telework' => $telework, 'user' =>  $this->user], function ($message) {
                foreach ($this->list_responsable as $resp) {
                    $message->to($resp['email']);
                }
                $message->subject('Response following the cancellation of teleworking');
            });
        }

        return $this->successResponse($telework);
    }

    public function RejetDefinitive(Request $request, $id_telework)
    {
        // $id_respons = Auth::user()['id'];
        $id_respons = User::where('id', $id_telework)->first();

        $telework = Telework::findOrFail($id_telework);
        $telework->update(['status' => 'Rejet définitif']);
        
        $telework_history = teleworkHistory::create([
            'id_responsible' => $id_respons,
            'status' => 'Rejet définitif',
            'is_archive' => 0,
            'is_rejected_prov' => 0,
            'raison_reject' => $request->raison_reject,
            'level' => $telework->level,
            'telework_id' => $id_telework,
        ]);

        $this->user = HolidayController::getUser($telework['user_id']);

        Mail::send('telework.RejetDefinitive', ['result' => $telework_history->raison_reject, 'telework' => $telework, 'user' =>  $this->user[0]], function ($message) {
            $message->to($this->user[0]['email']);
            $message->subject('Refusal of your remote work request');
        });

        return $this->successResponse($telework);
    }

    public function RejetProvisoire(RejetProvisoireRequest $request, $id_telework)
    {
        $validateData = $request->validated();
        // $id_respons = Auth::user()['id'];
        $id_respons = User::where('id', 1)->first()['id'];

        $telework = Telework::findOrFail($id_telework);
        $telework->update(['status' => "Rejet provisoire"]);

        $telework_history = teleworkHistory::create([
            'id_responsible' => $id_respons,
            'status' => "Rejet provisoire",
            'is_rejected_prov' => 0,
            'is_archive' => 0,
            'raison_reject' => $validateData['raison_reject'],
            'level' => $telework->level,
            'telework_id' => $id_telework,
        ]);

        $this->user = User::findOrFail($telework->user_id);
        $dates = json_decode($telework['date']);

        Mail::send('telework.RejetProvisoire', ['result' => $telework_history->raison_reject, 'dates' => $dates, 'user' =>  $this->user], function ($message) {
            $message->to($this->user['email']);
            $message->subject('Provisional rejection of your telework request');
        });

        return response()->json([
            'telework' => $telework,
            'telework_history' => $telework_history,
            'success' => true
        ], 200);
    }

    public function getTeleworkUser($id)
    {
        $nb_leaders = User::get_ids_leaders($id);
        $nb_chef = User::get_ids_department_chief($id);
        $nb_gerants = User::get_ids_gerants($id);

        $responsable_list = array_values(array_unique(array_merge($nb_leaders, $nb_chef, $nb_gerants)));
        $responsable = [];

        foreach ($responsable_list as $resp) {
            if ($resp != $id) {
                array_push($responsable, $resp);
            }
        }

        $teleworks = Telework::where([['user_id', '=', $id], ['status', '!=', "Rejet définitif"], ['status', '!=', "Annuler"], ['status', '!=', "Accepter"]])->with([
            'histories' => fn ($query) => $query->where([['id_responsible', '!=', $id]]),
        ])->get();

        // return $teleworks->sortBy('histories.created_at')->values()->all();;

        foreach ($teleworks as $tel) {
            if (count($tel['histories']) != 0) {
                foreach ($tel['histories'] as $history) {
                    $resp = User::findOrFail($history['id_responsible']);
                    $history['fullName'] = $resp['last_name'] . ' ' . $resp['first_name'];
                }
            }
        }

        foreach ($teleworks as $telework) {
            $rest = 0;
            $list = [];
            foreach ($telework['histories'] as $c) {
                if ($c['is_rejected_prov'] == 0) {
                    if ($c['status'] == 'Rejet provisoire') {
                        array_push($list, -1);
                    } else if ($c['status'] == 'Rejet définitif') {
                        array_push($list, 'x');
                    } else if ($c['status'] == 'Accepter') {
                        array_push($list, $c['id_responsible']);
                    }
                }
            }

            $list = array_values(array_unique($list));
            $rest = count($responsable) - count($list);

            $telework['rest'] = $rest;
            $telework['nb_responsable'] = count($responsable);

            $telework['nb_acceptation'] = $list;
        }
        return $teleworks;
    }

    //Historiques de mes demandes 
    //Historiques de chaque demande 
    public function getTeleworkUserHistories($id)
    {
        $nb_leaders = User::get_ids_leaders($id);
        $nb_chef = User::get_ids_department_chief($id);
        $nb_gerants = User::get_ids_gerants($id);

        $responsable_list = array_values(array_unique(array_merge($nb_leaders, $nb_chef, $nb_gerants)));
        $responsable = [];

        foreach ($responsable_list as $resp) {
            if ($resp != $id) {
                array_push($responsable, $resp);
            }
        }
        $teleworks = Telework::where([['user_id', '=', $id], ['status', '=', "Rejet définitif"]])
            ->orwhere([['user_id', '=', $id], ['status', '=', "Accepter"]])
            ->orwhere([['user_id', '=', $id], ['status', '=', "Annuler"]])
            ->with([
                'histories' => fn ($query) => $query->where([['id_responsible', '!=', $id]]),
            ])->get();

        foreach ($teleworks as $tel) {
            if (count($tel['histories']) != 0) {
                foreach ($tel['histories'] as $history) {
                    $resp = User::findOrFail($history['id_responsible']);
                    $history['fullName'] = $resp['last_name'] . ' ' . $resp['first_name'];
                }
            }
        }

        foreach ($teleworks as $telework) {
            $rest = 0;
            $list = [];
            foreach ($telework['histories'] as $c) {
                if ($c['is_rejected_prov'] == 0) {
                    if ($c['status'] == 'Rejet provisoire') {
                        array_push($list, -1);
                    } else if ($c['status'] == 'Rejet définitif') {
                        array_push($list, 'x');
                    } else if ($c['status'] == 'Accepter') {
                        array_push($list, $c['id_responsible']);
                    }
                }
            }

            $list = array_values(array_unique($list));
            $rest = count($responsable) - count($list);

            $telework['rest'] = $rest;
            $telework['nb_responsable'] = count($responsable);

            $telework['nb_acceptation'] = $list;
        }
        return $teleworks;
    }

    public function getAllTeleworkLeaderHistories($id_auth)
    {
        // TeleworkControlle::getNbLeaders()
        $List_teleworks = [];
        // afficher list pour le responsable !!!!
        $List_team = TeamUser::where([['user_id', '=', $id_auth], ['is_leader', '=', 1]])->get();
        $team_id = [];

        // return id team eli appartient liha el id_auth kenou leader ala equipe !!!
        if (count($List_team) != 0) {
            foreach ($List_team as $team) {
                array_push($team_id, $team['team_id']);
            }
        } else {
            $team_id =  null;
        }

        $teleworks = Telework::where([['status', '!=', 'Envoyé'], ['user_id', '!=', $id_auth]])->with([
            'histories' => fn ($query) => $query->where([['is_rejected_prov', '=', 0]]),
            'user' => fn ($query) => $query->with([
                'teams' => ([
                    'team' => fn ($query) => $query->where([['status', '=', 'active']])->with([
                        'department' => fn ($query) => $query->where([['status', '=', 'active']])->pluck('id')
                    ])
                ])
            ])
        ])->get();

        $user_team = [];
        foreach ($teleworks as $telework) {
            foreach ($telework['user']['teams'] as $team) {
                if ($team_id != null) {
                    if (in_array($team['team_id'], $team_id)) {
                        array_push($List_teleworks, $telework);
                        $user_team = array_values(array_unique($List_teleworks));
                    }
                }
            }
        }

        $List_teleworks_final = [];
        foreach ($user_team as $telework) {
            if (count($telework['histories']) == 0) {
                array_push($List_teleworks_final, $telework);
            } else {
                foreach ($telework['histories'] as $history) {
                    array_push($List_teleworks_final, $telework);
                }
            }
        }
        return $result = array_values(array_unique($List_teleworks_final));
    }

    public function getAllTeleworkChefDepartmentHistories($id_auth)
    {
        $List_teleworks = [];
        $List_department = Department::where([['department_chief', '=', $id_auth]])->get();
        $List_id_department = [];

        // return id team eli appartient liha el id_auth kenou leader ala equipe !!!
        if (count($List_department) != 0) {
            foreach ($List_department as $dep) {
                array_push($List_id_department, $dep['id']);
            }
        } else {
            $List_id_department =  null;
        }

        $teleworks = Telework::where([['status', '!=', 'Envoyé'], ['user_id', '!=', $id_auth]])->with([
            'histories' => fn ($query) => $query->where([['is_rejected_prov', '=', 0], ['level', '=', 2]]),
            'user' => fn ($query) => $query->with([
                'teams' => ([
                    'team' => fn ($query) => $query->where([['status', '=', 'active']])->with([
                        'department' => fn ($query) => $query->where([['status', '=', 'active']])->pluck('id')
                    ])
                ])
            ])
        ])->get();

        $user_dep = [];

        foreach ($teleworks as $telework) {
            foreach ($telework['user']['teams'] as $team) {
                if ($team['team'] != null && $List_id_department != []) {
                    if (in_array($team['team']['department']['id'], $List_id_department)) {
                        array_push($List_teleworks, $telework);
                        $user_dep = array_values(array_unique($List_teleworks));
                    }
                }
            }
        }
        $rep_responsable = false;
        $List_teleworks_final = [];

        foreach ($teleworks as $telework) {
            if (count($telework['histories']) == 0) {
            } else {
                foreach ($telework['histories'] as $history) {
                    if ($history['id_responsible'] == $id_auth) {
                        array_push($List_teleworks_final, $telework);
                    }
                }
            }
        }
        return $result = array_values(array_unique($List_teleworks_final));
    }

    public function getAllTeleworkGerantHistories($id_auth)
    {
        // afficher list pour le gerant !!!!
        $result_gerants = Telework::where([['status', '!=', 'Envoyé'], ['user_id', '!=', $id_auth]])->with([
            'histories' => fn ($query) => $query->where([['is_rejected_prov', '=', 0]]),
            'user' => fn ($query) => $query->with([
                'teams' => ([
                    'team' => fn ($query) => $query->where([['status', '=', 'active']])->with([
                        'department' => fn ($query) => $query->where([['status', '=', 'active']])->pluck('id')
                    ])
                ])
            ])
        ])->get();


        $rep_responsable = false;
        $List_teleworks_final = [];

        foreach ($result_gerants as $telework) {

            if (count($telework['histories']) == 0) {
            } else {
                foreach ($telework['histories'] as $history) {
                    if ($history['id_responsible'] == $id_auth) {
                        array_push($List_teleworks_final, $telework);
                    }
                }
            }
        }
        return $result = array_values(array_unique($List_teleworks_final));
    }

    public function getAllTeleworksHistories($id_auth)
    {
        $result = [];
        // ken user auth = leader !!
        $test_fonction = HolidayController::determineUserRoleStatus($id_auth);

        if ($test_fonction['leader'] == 1) {
            $result = TeleworkController::getAllTeleworkLeaderHistories($id_auth);
        }
        if ($test_fonction['department_chief'] == 1) {
            $result = TeleworkController::getAllTeleworkChefDepartmentHistories($id_auth);
        }
        if ($test_fonction['gerant'] == 1) {
            $result = TeleworkController::getAllTeleworkGerantHistories($id_auth);
        }

        if ($test_fonction['leader'] == 1 && $test_fonction['department_chief'] == 1) {
            $result_1 = TeleworkController::getAllTeleworkLeaderHistories($id_auth);
            $result_2 = TeleworkController::getAllTeleworkChefDepartmentHistories($id_auth);
            $result = array_merge($result_1, $result_2);
        }

        if ($test_fonction['gerant'] == 1 && $test_fonction['department_chief'] == 1) {
            $result_1 = TeleworkController::getAllTeleworkGerantHistories($id_auth);
            $result_2 = TeleworkController::getAllTeleworkChefDepartmentHistories($id_auth);
            $result = array_merge($result_1, $result_2);
        }

        if ($test_fonction['leader'] == 1 && $test_fonction['gerant'] == 1) {
            $result_1 = TeleworkController::getAllTeleworkLeaderHistories($id_auth);
            $result_2 = TeleworkController::getAllTeleworkGerantHistories($id_auth);
            $result = array_merge($result_1, $result_2);
        }

        if ($test_fonction['leader'] == 1 && $test_fonction['gerant'] == 1 && $test_fonction['department_chief'] == 1) {
            $result_1 = TeleworkController::getAllTeleworkLeaderHistories($id_auth);
            $result_2 = TeleworkController::getAllTeleworkGerantHistories($id_auth);
            $result_3 = TeleworkController::getAllTeleworkChefDepartmentHistories($id_auth);
            $result = array_merge($result_1, $result_2, $result_3);
        }

        foreach ($result as $tel) {
            if (count($tel['histories']) != 0) {
                foreach ($tel['histories'] as $history) {
                    $resp = User::findOrFail($history['id_responsible']);
                    $history['fullName'] = $resp['last_name'] . ' ' . $resp['first_name'];
                }
            }
        }

        return $this->successResponse(array_values(array_unique($result)));
    }
}
