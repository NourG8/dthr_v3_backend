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
        $company = CompanyController::getOneCompany();

        return $this->successResponse([
            'user' => $user,
            'company' => $company,
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

    //   /**
    //  * @return \Illuminate\Http\JsonResponse
    //  */
    // public function forgotPassword(StoreForgotPasswordRequest $request)
    // {
    //     $validatedRequest = $request->validated([
    //         'email' => 'required|email|exists:users',
    //   ]);
    
    //     $user = User::where('email', $validatedRequest['email'])
    //                  ->whereNotNull('password')
    //                  ->firstOrFail();

    //     PasswordReset::updateOrInsert(['email' => $email], $code);
        
    //     $status = Password::sendResetLink($request->only('email'));
    
    //     if ($status === Password::RESET_LINK_SENT) 
    //     {
    //         return $this->successResponse('Réinitialisation du mot de passe envoyée avec succès à l\'utilisateur', 200);
    //     } 
    //     else 
    //     {
    //         return $this->errorResponse('forgot_fail', 400);
    //     }
    // }
    


    
}



