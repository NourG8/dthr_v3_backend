<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeleworkController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\RolesPermissionsController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentTypeController;

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


Route::post('/login', [AuthController::class, 'login']);
// Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
// Route::post('/reset-password', [AuthController::class, 'resetPassword']);
// Route::post('/verif-code', [AuthController::class, 'verifyResetPasswordCode']);

Route::middleware('auth:sanctum')->group(function () {
  // return $request->user();

  Route::post('/logout', [AuthController::class, 'logout']);

  Route::get("users", [UserController::class, 'getAllUsers']);
  Route::get("users/manager", [UserController::class, 'getAllUserManager']);
  Route::get("user/list/archive", [UserController::class, 'getArchivedUser']);
  Route::post("users", [UserController::class, 'AddUser']);
  Route::put("users/{id}", [UserController::class, 'editUser']);
  Route::put("user_document/delete/{id}", [UserController::class, 'DeleteContractsUser']);
  Route::get("users/delete/{id}", [UserController::class, 'destroyUser']);
  Route::put("user/archive/{id}", [UserController::class, 'archiveUser']);
  Route::put("user/reset/{id}", [UserController::class, 'resetUser']);
  Route::get("users/model/{id}", [UserController::class, 'getContractsUserModel']);
  Route::get("users/document/{id}", [UserController::class, 'getContractsUserSigned']);
  Route::get("user/teams/{id}", [UserController::class, 'getTeamsDepartment']);
  Route::put("changerImg/{id}", [UserController::class, 'ChangePhotoProfil']);
  Route::post("user/upload/old_document/{id_user}", [UserController::class, 'uploadOldContract']);
  Route::get("user_document/download/{id_user_document}", [UserController::class, 'downloadOldContract']);
  Route::get("user/document/{id}", [UserController::class, 'getAllContractsUser']);
  //check data exist
  Route::post("users/check-cin", [UserController::class, 'checkUserCin']);
  Route::post("users/check-passport", [UserController::class, 'checkUserPassport']);
  Route::post("users/check-phone", [UserController::class, 'checkUserPhone']);
  Route::post("users/check-phone-emergency", [UserController::class, 'checkUserPhoneEmergency']);
  Route::post("users/check-email-prof", [UserController::class, 'checkUserEmailProf']);
  Route::post("users/check-email", [UserController::class, 'checkUserEmail']);

  //POSITION USER
  Route::get("user/position/{id}", [UserController::class, 'getPositionUser']);
  Route::put("user/edit/{id}", [UserController::class, 'editUserIntern']);
  Route::post("editContractUser/{id_user_contract}", [UserController::class, 'editContractsToUser']);

  Route::group(['prefix' => 'users'], function () {
    Route::post('/roles/{id}', [UserController::class, 'assignRoles']);
    Route::post('/permissions/{id}', [UserController::class, 'assignPermissions']);
  });

  //CRUD operations company
  Route::post("company", [CompanyController::class, 'AddCompany']);
  Route::put("company/{id}", [CompanyController::class, 'editCompany']);
  Route::put("changer/photo/{id}", [CompanyController::class, 'ChangePhoto']);
  Route::get("company", [CompanyController::class, 'getCompany']);

  Route::get("get-user/{id}", [HolidayController::class, 'getUser']);
  Route::get("get-leader/{id}", [HolidayController::class, 'getLeader']);
  Route::get("get-chefdep/{id}", [HolidayController::class, 'getChiefDepartement']);
  Route::get("get_ids_leaders/{id}", [HolidayController::class, 'get_ids_leaders']);
  Route::get("get_ids_chef_dep/{id}", [HolidayController::class, 'get_ids_department_chief']);
  Route::get("get_ids_gerants", [HolidayController::class, 'get_ids_gerants']);

  Route::get("get-gerant", [HolidayController::class, 'getAllGerants']);
  Route::get("user/conge/{id}", [HolidayController::class, 'getHolidayUser']);
  Route::get("user/historique/conge/{id}", [HolidayController::class, 'getHistoriqueHolidayUser']);
  Route::get("test/{id}", [HolidayController::class, 'test_Leader_ChefDep_Gerant']);
  Route::get("conge-leader/{id}", [HolidayController::class, 'getAllHolidayLeader']);
  Route::get("conge-chief-dep/{id}", [HolidayController::class, 'getAllHolidayChefDepartment']);
  Route::get("conge-gerant/{id}", [HolidayController::class, 'getAllHolidayGerant']);

  //test 
  // Route::get("proceesGerant/{id}", [HolidayController::class, 'processGerant']);

  Route::get("conges/{id}", [HolidayController::class, 'getAllHoliday']);
  Route::get("histories-conge-leader/{id}", [HolidayController::class, 'getHistoriqueHolidayLeader']);
  Route::get("historiques/conges/{id}", [HolidayController::class, 'getHistoriqueHoliday']);
  Route::post("conge/{id}", [HolidayController::class, 'AddHoliday']);
  Route::put("conge/{id}", [HolidayController::class, 'updateHoliday']);
  Route::put("annuler/conge/{id}", [HolidayController::class, 'AnnulerHoliday']);

  Route::put("rejet/provisoire/conge/{id}", [HolidayController::class, 'RejetProvisoire']);
  Route::put("rejet/definitive/conge/{id}", [HolidayController::class, 'RejetDefinitive']);

  Route::put("accepter/conge/{id}", [HolidayController::class, 'accepterHoliday']);
  Route::put("annuler/conge/{id}", [HolidayController::class, 'AnnulerHoliday']);

  // CRUD operations position
  Route::get("positions", [PositionController::class, 'getAllPositions']);
  Route::get("positions/delete/{id}", [PositionController::class, 'destroyPosition']);
  Route::post("positions", [PositionController::class, 'AddPosition']);
  Route::put("positions/{id}", [PositionController::class, 'editPosition']);
  Route::put("position/archive/{id}", [PositionController::class, 'archivePosition']);
  Route::get("position/list/archive", [PositionController::class, 'getArchivedPosition']);
  Route::put("position/reset/{id}", [PositionController::class, 'resetPosition']);
  Route::get("position/getnb_Pos/{id}", [PositionController::class, 'getNb_Users_in_Pos']);

  // CRUD operations teams
  Route::post("team", [TeamController::class, 'addTeams']);
  Route::put("delete/team/{id}", [TeamController::class, 'deleteTeams']);
  Route::put("desactiver/team/{id}", [TeamController::class, 'desactiverTeams']);
  Route::put("activer/team/{id}", [TeamController::class, 'activerTeams']);
  Route::put("update/team/{id}", [TeamController::class, 'updateTeams']);
  Route::get("teams", [TeamController::class, 'getTeams']);
  Route::get("teams/archive", [TeamController::class, 'getAllArchiveTeams']);
  Route::get("users/manager", [UserController::class, 'getAllUserManager']);
  Route::get("users/teams/{id}", [TeamController::class, 'getUsersInTeams']);
  Route::put("delete/user/team/{id}", [TeamController::class, 'deleteUserTeams']);

  //CRUD departments
  Route::get("departments", [DepartmentController::class, 'getAllDepartments']);
  Route::put("departments/delete/{id}", [DepartmentController::class, 'destroyDepartment']);
  Route::post("departments", [DepartmentController::class, 'AddDepartment']);
  Route::put("departments/{id}", [DepartmentController::class, 'editDepartment']);
  Route::put("departments/archive/{id}", [DepartmentController::class, 'archiveDepartment']);
  Route::get("department/list/archive", [DepartmentController::class, 'getArchivedDepartment']);
  Route::get("department/user/{id_dep}", [DepartmentController::class, 'getUsersActiveDepartment']);
  Route::get("getNb_team/{id}", [DepartmentController::class, 'getNb_team_in_dep']);
  Route::get("getNb_team_Archive/{id}", [DepartmentController::class, 'getNb_team_in_dep_Archive']);
  Route::get("getNb_Users/{id}", [DepartmentController::class, 'getNb_Users_in_dep']);
  Route::put("department/reset/{id}", [DepartmentController::class, 'reactivateDepartment']);

  // CRUD Telework
  Route::get("telework/{id}", [TeleworkController::class, 'getAllTeleworks']);
  Route::get("/telework/histories/{id}", [TeleworkController::class, 'getAllTeleworksHistories']);
  Route::get("/getuser/teleworks", [TeleworkController::class, 'getTeleworksUser']);
  Route::get("telework/delete/{id}", [TeleworkController::class, 'destroyTelework']);
  Route::post("telework", [TeleworkController::class, 'AddTelework']);
  Route::put("telework/{id}", [TeleworkController::class, 'editTelework']);

  Route::get("telework/refuse/{id}", [TeleworkController::class, 'refuseTelework']);
  Route::get("getAllTeleworkLeader/{id}", [TeleworkController::class, 'getAllTeleworkLeader']);
  Route::get("user/telework/{id}", [TeleworkController::class, 'getTeleworkUser']);

  Route::get("acceptLeader/{id}", [TeleworkController::class, 'acceptTelLeader']);
  Route::get("acceptTelChefDep/{id}", [TeleworkController::class, 'acceptTelChefDep']);
  Route::get("acceptTelGerant/{id}", [TeleworkController::class, 'acceptTelGerant']);
  Route::get("accepter/{id}", [TeleworkController::class, 'accepter']);
  Route::get("getNbLeaders/{id}", [TeleworkController::class, 'getNbLeaders']);
  Route::get("getNbGerants", [TeleworkController::class, 'getNbGerants']);
  Route::get("getNbChefDep/{id}", [TeleworkController::class, 'getNbChefDep']);
  Route::get("responsables/{id}", [TeleworkController::class, 'ResponsableAddTelework']);

  Route::post("rejetProvisoire/{id}", [TeleworkController::class, 'RejetProvisoire']);
  Route::post("rejetDefinitive/{id}", [TeleworkController::class, 'RejetDefinitive']);

  Route::get("annulerTelework/{id}", [TeleworkController::class, 'AnnulerTelework']);
  Route::get("teleworksHistoriques/{id}", [TeleworkController::class, 'getTeleworkUserHistories']);

  // PERMISSIONS
  Route::group(['prefix' => 'permissions'], function () {
    Route::post('/many', [PermissionsController::class, 'storeMany']);
    Route::get('/', [PermissionsController::class, 'index']);
    Route::get('/archive', [PermissionsController::class, 'getArchivedPermissions']);
    Route::get('/{id}', [PermissionsController::class, 'show']);
    Route::post('/', [PermissionsController::class, 'store']);
    Route::put('/{id}', [PermissionsController::class, 'update']);
    Route::delete('/{id}', [PermissionsController::class, 'destroy']);
    Route::put('/restore/{id}', [PermissionsController::class, 'restorePermission']);
  });

  // ROLES
  Route::group(['prefix' => 'roles'], function () {
    Route::post('/{id}/permissions', [RolesPermissionsController::class, 'addPermissions']);
    Route::get('/', [RolesPermissionsController::class, 'index']);
    Route::get('/archive', [RolesPermissionsController::class, 'getArchivedRoles']);
    Route::get('/{id}', [RolesPermissionsController::class, 'show']);
    Route::post('/', [RolesPermissionsController::class, 'store']);
    Route::put('/{id}', [RolesPermissionsController::class, 'update']);
    Route::delete('/{id}', [RolesPermissionsController::class, 'destroy']);
    Route::put('/restore/{id}', [RolesPermissionsController::class, 'restoreRole']);
  });

  // DOCUMENT
  Route::group(['prefix' => 'documents'], function () {
    Route::get('/archive', [DocumentController::class, 'getAllArchiveDocuments']);
    Route::put('/activate/{id}', [DocumentController::class, 'activateDocument']);
    Route::put('deactivate/{id}', [DocumentController::class, 'deactivateDocument']);
    Route::get('/', [DocumentController::class, 'index']);
    Route::get('/{id}', [DocumentController::class, 'show']);
    Route::post('/', [DocumentController::class, 'store']);
    Route::put('/{id}', [DocumentController::class, 'update']);
    Route::delete('/{id}', [DocumentController::class, 'destroy']);
  });

  // DOCUMENT TYPE
  Route::group(['prefix' => 'documents-type'], function () {
    Route::get('/archive', [DocumentTypeController::class, 'getAllArchiveDocuments']);
    Route::put('/activate/{id}', [DocumentTypeController::class, 'activateDocument']);
    Route::put('deactivate/{id}', [DocumentTypeController::class, 'deactivateDocument']);
    Route::get('/', [DocumentTypeController::class, 'index']);
    Route::get('/{id}', [DocumentTypeController::class, 'show']);
    Route::post('/', [DocumentTypeController::class, 'store']);
    Route::put('/{id}', [DocumentTypeController::class, 'update']);
    Route::delete('/{id}', [DocumentTypeController::class, 'destroy']);
  });

  Route::get('/generate-pdf/{id}', [UserController::class, 'generatePDF']);
  Route::get("user/download/old_contract/{id_user_contract}", [UserController::class, 'downloadModelContracts']);

});
