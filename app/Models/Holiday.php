<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use HasFactory , SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'dates' => 'json'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function histories()
    {
        return $this->hasMany(HolidayHistory::class);
    }

    public static function getAllCongeUser($id_user)
    {
        $conges = Holiday::where([['user_id', '=', $id_user],['status', '!=', 'annuler'],['status', '!=', 'accepter'],['status', '!=', 'rejet définitif']])->with([
            'histories' => fn($query) => $query->where([['id_responsible', '!=', $id_user]]),
        ])->get();

        foreach ($conges as $conge) {
            if(count($conge['histories']) != 0){
                foreach ($conge['histories'] as $history) {
                    $responsable = User::findOrFail($history['id_responsible']);
                    $history['fullName'] = $responsable['last_name'] .' '. $responsable['first_name'];
                }
            }

        }

        return $conges;
    }
    
}
