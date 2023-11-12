<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\HolidayController;

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

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
// Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
// Route::post('/reset-password', [AuthController::class, 'resetPassword']);
// Route::post('/verif-code', [AuthController::class, 'verifyResetPasswordCode']);

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

  //CRUD operations company
  Route::post("company", [CompanyController::class, 'AddCompany']);
  Route::put("company/{id}", [CompanyController::class, 'editCompany']);
  Route::put("changer/photo/{id}", [CompanyController::class, 'ChangePhoto']);
  Route::get("company", [CompanyController::class, 'getCompany']);

  Route::get("get-user/{id}", [HolidayController::class, 'getUser']);
  Route::get("get-leader/{id}", [HolidayController::class, 'getLeader']);
  Route::get("get-chefdep/{id}", [HolidayController::class, 'getChiefDepartement']);
  Route::get("get_ids_leaders/{id}", [HolidayController::class, 'get_ids_leaders']);
  Route::get("get_ids_chef_dep/{id}", [HolidayController::class, 'get_ids_chef_dep']);
  Route::get("get_ids_gerants/{id}", [HolidayController::class, 'get_ids_gerants']);

  Route::get("get-gerant", [HolidayController::class, 'getAllGerants']);
  Route::get("user/conge/{id}", [HolidayController::class, 'getHolidayUser']);
  Route::get("user/historique/conge/{id}", [HolidayController::class, 'getHistoriqueHolidayUser']);
  Route::get("test/{id}", [HolidayController::class, 'test_Leader_ChefDep_Gerant']);
  Route::get("conge-leader/{id}", [HolidayController::class, 'getAllHolidayLeader']);
  Route::get("conge-chief-dep/{id}", [HolidayController::class, 'getAllHolidayChefDepartment']);
  Route::get("conge-gerant/{id}", [HolidayController::class, 'getAllHolidayGerant']);

  Route::get("conges/{id}", [HolidayController::class, 'getAllHoliday']);
  Route::get("histories-conge-leader/{id}", [HolidayController::class, 'getHistoriqueHolidayLeader']);
  Route::get("historiques/conges/{id}", [HolidayController::class, 'getHistoriqueHoliday']);
  Route::post("conge/{id}", [HolidayController::class, 'AddHoliday']);
  Route::put("conge/{id}", [HolidayController::class, 'updateHoliday']);
  Route::put("annuler/conge/{id}", [HolidayController::class, 'AnnulerHoliday']);

  Route::put("rejet/provisoire/conge/{id}", [HolidayController::class, 'RejetProvisoire']);
  Route::put("rejet/definitive/conge/{id}", [HolidayController::class, 'RejetDefinitive']);

  

