<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class HolidayHistory extends Model
{
    use HasFactory , SoftDeletes;

    protected $guarded = [];

    public function holiday()
    {
        return $this->belongsTo(Holiday::class);
    }

    public static function getFilteredHolidays($id_auth, $team_id = null, $List_id_department = null)
    {
        $query = Holiday::where([['user_id', '!=', $id_auth]])
            ->with('histories');
    
        if ($team_id !== null) {
            $query->whereHas('user.teams.team', function ($query) use ($team_id) {
                $query->where([['status', '=', 'active']])->whereIn('id', $team_id);
            });
        } elseif ($List_id_department !== null) {
            $query->where([
                ['level', '!=', 1],
                ['level', '!=', 2],
                ['status', '!=', 'Envoye'],
                ['user_id', '!=', $id_auth]
            ])->orWhere([
                ['level', '!=', 2],
                ['status', '!=', 'Envoye'],
                ['user_id', '!=', $id_auth]
            ])->with([
                'user.teams.team.department' => fn ($query) => $query->where('status', '=', 'active')->pluck('id')
            ])->whereHas('user.teams.team.department', function ($query) use ($List_id_department) {
                $query->where([['status', '=', 'active']])->whereIn('id', $List_id_department);
            });
        }
    
        return $query->get();
    }

    public static function filterUserDepartments($conges, $List_id_department)
    {
        return $conges->filter(function ($conge) use ($List_id_department) {
            return collect($conge['user']['teams'])->filter(function ($team) use ($List_id_department) {
                return $team['team'] != null && $List_id_department != [] &&
                    in_array($team['team']['department']['id'], $List_id_department);
            })->isNotEmpty();
        });
    }

    public static function processHolidayHistoriesForLeadership($conge, &$List_conges_final, $id_auth)
    {
        $tab_ids = [];
        $date_final = Carbon::parse($conge->date)->format('d M Y');
        $conge['date'] = $date_final;
    
        if (count($conge['histories']) != 0) {
            $conge['histories']->each(function ($history) use (&$List_conges_final, $id_auth, &$tab_ids, $conge) {
                $responsable = User::findOrFail($history['id_responsible']);
                $history['fullName'] = $responsable['last_name'] . ' ' . $responsable['first_name'];
    
                $date = Carbon::parse($history->created_at)->format('d M Y');
                $history['date'] = $date;
                array_push($tab_ids, $history['id_responsible']);
    
                if ($history['id_responsible'] == $id_auth) {
                    array_push($List_conges_final, $conge);
                }
    
                $conge['tab_ids_Final'] = array_values(array_unique($tab_ids));
            });
        }
    }
    
    public static function processHistoriesForChefDepartment($user_dep, $id_auth)
    {
        $List_conges_final = [];
    
        $user_dep->each(function ($conge) use (&$List_conges_final, $id_auth) {
            $date_final = Carbon::parse($conge->date)->format('d M Y');
            $conge['date'] = $date_final;
    
            collect($conge['histories'])
                ->filter(function ($history) use ($id_auth) {
                    $responsable = User::findOrFail($history['id_responsible']);
                    $history['fullName'] = $responsable['last_name'] . ' ' . $responsable['first_name'];
                    $date = Carbon::parse($history->created_at)->format('d M Y');
                    $history['date'] = $date;
    
                    return $history['id_responsible'] == $id_auth;
                })
                ->each(function () use ($conge, &$List_conges_final) {
                    array_push($List_conges_final, $conge);
                });
        });
    
        return $List_conges_final;
    }

    public static function processHistoriesForGerant($result_gerants, $id_auth)
    {
        $List_conges_final = [];
    
        $result_gerants->each(function ($conge) use (&$List_conges_final, $id_auth) {
            $date_final = Carbon::parse($conge->date)->format('d M Y');
            $conge['date'] = $date_final;
    
            collect($conge['histories'])
                ->filter(function ($history) use ($id_auth) {
                    $responsable = User::findOrFail($history['id_responsible']);
                    $history['fullName'] = $responsable['last_name'] . ' ' . $responsable['first_name'];
                    $date = Carbon::parse($history->created_at)->format('d M Y');
                    $history['date'] = $date;
    
                    return $history['id_responsible'] == $id_auth;
                })
                ->each(function () use ($conge, &$List_conges_final) {
                    array_push($List_conges_final, $conge);
                });
        });
    
        return $List_conges_final;
    }

    public static function createHolidayHistory($id_responsible, $status, $is_rejected_prov, $is_archive, $level, $id_conge, $raison_reject = null)
    {
        $conge_history = new HolidayHistory();
        $conge_history->id_responsible = $id_responsible;
        $conge_history->status = $status;
        $conge_history->is_rejected_prov = $is_rejected_prov;
        $conge_history->is_archive = $is_archive;
        $conge_history->level = $level;
        $conge_history->holiday_id = $id_conge;
        $conge_history->raison_reject = $raison_reject;
        $conge_history->save();

        return $conge_history;
    }

    public static function updateHolidayStatus($id_conge, $status, $level = null)
    {
        $now = Carbon::now();
        Holiday::where([
            ['id', '=', $id_conge],
            ['level', '=', $level],
        ])->update(['status' => $status, 'date' => $now]);
    }

}
