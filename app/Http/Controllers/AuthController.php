<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;

use Illuminate\Support\Facades\Hash;
use App\Http\Requests\Auth\LoginUserRequest;
use Illuminate\Support\Facades\Auth;
use DB;
use App\Models\User;

class AuthController extends Controller
{
    public function login(LoginUserRequest $request)
    {
        $validatedUser = $request->validated();

        if (!Auth::attempt($validatedUser)) {
            return $this->errorResponse('login_fail', 401);

        } else {
            $user = Auth::user();
            $token = $user->createToken('api_token')->plainTextToken;
        }

        $user->makeVisible('password');
        $permissions = $user->getAllPermissions()->unique('id')->values();
        $user->load(['roles']);

        return $this->successResponse([
            'user' => $user,
            'permissions' => isset($permissions) ? $permissions : [],
            'token' => $token,
            'message' => 'login_success'
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        $user->devices()->delete();

        return $this->successResponse(['message' => 'authentication.logout_success'], 200);
    }


    
}



