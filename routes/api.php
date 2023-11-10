<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get("users", [UserController::class, 'getAllUsers']);
Route::get("users/manager", [UserController::class, 'getAllUserManager']);
Route::get("user/list/archive", [UserController::class, 'getArchivedUser']);
Route::post("users", [UserController::class, 'AddUser']);
Route::put("users/{id}", [UserController::class, 'editUser']);
Route::put("user_contract/delete/{id}", [UserController::class, 'DeleteContractsUser']);
Route::get("users/delete/{id}", [UserController::class, 'destroyUser']);
Route::put("user/archive/{id}", [UserController::class, 'archiveUser']);
Route::put("user/reset/{id}", [UserController::class, 'resetUser']);
Route::get("users/model/{id}", [UserController::class, 'getContractsUserModel']);
Route::get("users/contract/{id}", [UserController::class, 'getContractsUserSigned']);
Route::get("user/teams", [UserController::class, 'getTeamsDepartment']);
Route::put("changerImg/{id}", [UserController::class, 'ChangePhotoProfil']);
Route::post("user/upload/old_contract/{id_user}", [UserController::class, 'uploadOldContract']);
Route::get("user_contract/download/{id_user_contract}", [UserController::class, 'DownloadOldContract']);
Route::get("user/contract/{id}", [UserController::class, 'getAllContractsUser']);


