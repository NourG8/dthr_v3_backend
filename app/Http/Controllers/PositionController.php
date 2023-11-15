<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\User;
use App\Models\PositionUser;
use App\Http\Requests\Position\PositionRequest;

class PositionController extends Controller
{
    public function getAllPositions()
    {
        $positions = Position::where('status', 'active')->get();

         return $this->successResponse($positions);
    }

    public function AddPosition(PositionRequest  $request)
    {
        $position = Position::create(array_merge($request->validated(), ['status' => 'active']));

        return $this->successResponse($position);
    }

    public function editPosition(PositionRequest $request,$id)
    {
        $position = Position::findOrFail($id);
        $position->update($request->validated());

        return $this->successResponse($position);
    }

    public function destroyPosition($id)
    {
        $position = Position::findOrFail($id);
        $position->delete();
    
        return $this->successResponse($position);
    }

    public function archivePosition($id)
    {
        $position = Position::findOrFail($id);
        $position->update(['status' => 'inactive']);

        return $this->successResponse($position);
    }

    public function resetPosition($id)
    {
        $position = Position::with(['users' => function ($query) {
            $query->whereNull('position_users.end_date');
        }])->findOrFail($id);

        if ($position->users) {
            $position->update(['status' => 'active']);
        }

        return $this->successResponse($position);
    }

    public function getArchivedPosition()
    {
        $positions = Position::where('status', '!=', 'active')->get();

        return $this->successResponse($positions);
    }

    public function getNb_Users_in_Pos($id)
    {
        $position_id = Position::where("status","active")->where("id",$id)->first('id'); 
        $nb_users_position = PositionUser::where("position_id", $position_id['id'])->whereNull('end_date')
        ->whereHas('user', function ($query) {
            $query->where('status', 'active');
        })->count();

        return $this->successResponse($nb_users_position);
    }

}
