<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PositionUser;
use App\Models\Team;
use App\Models\UserDocument;
use App\Http\Requests\Users\UserRequest;
use App\Http\Requests\Users\UserEditRequest;
use App\Http\Requests\Contracts\OldContractRequest;
use App\Http\Requests\Users\EditUserInternRequest;
use App\Http\Requests\Users\UserPermissionsRequest;
use App\Http\Requests\Users\UserRolesRequest;
use App\Models\Document;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use PDF;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\PhpWord;

class UserController extends Controller
{
    public function getAllUsers()
    {
        $users = User::get();
        return $this->successResponse($users);
    }

    public function getAllUserManager()
    {
        $managers = User::where([
            ['is_deleted', false],
            ['status', 'active'],
        ])
            ->whereHas('positions', function ($q) {
                $q->whereHas('position', function ($query) {
                    $query->where([
                        ['job_name', 'Manager'],
                        ['is_deleted', false],
                        ['status', 'active'],
                    ]);
                });
            })
            ->get();

        return $this->successResponse($managers);
    }

    public function getArchivedUser()
    {
        $users = User::where('status', 'inactive')
            ->whereHas('positions', function ($query) {
                $query->where('end_date', '=', null);
            })
            ->get();

        return $this->successResponse($users);
    }

    public function AddUser(UserRequest $request)
    {
        $user = User::create(array_merge($request->validated(), [
            'image' => null,
            'status' => 'active',
            'pwd_reset_admin' => 0,
            'password' => Hash::make(Str::random(8)),
        ]));

        $user->positions()->create([
            'position_id' => $request->input('position_id'),
            'start_date' => now(),
            'end_date' => null
        ]);

        $user->teams()->create([
            'team_id' => $request->input('team_id'),
            'is_leader' => 0,
            'integration_date' => now(),
        ]);

        $user->load('positions', 'teams');

        return $this->successResponse($user, 201);
    }

    public function DeleteContractsUser($id)
    {
        $userContract = UserDocument::findOrFail($id);
        $userContract->update(['is_deleted' => 1]);

        return  $this->successResponse(['message' => 'user document deleted successfully']);
    }

    public function assignRoles($id, UserRolesRequest $request)
    {
        // $this->authorize('store', Role::class);

        $validatedData = $request->validated();

        $user = User::findOrFail($id);
        $roles = Role::whereIn('name', $validatedData['roles'])->get();

        if ($request->type === 'attach') {
            $user->assignRole($roles);
        }

        if ($request->type === 'sync') {
            $user->syncRoles($roles);
        }

        $user->load('roles');

        return $this->successResponse($user);
    }

    public function assignPermissions($id, UserPermissionsRequest $request)
    {
        // $this->authorize('store', Role::class);

        $validatedData = $request->validated();

        $user = User::findOrFail($id);
        $permissions = Permission::whereIn('name', $validatedData['permissions'])->get();

        if ($request->type === 'attach') {
            $user->givePermissionTo($permissions);
        }

        if ($request->type === 'sync') {
            $user->syncPermissions($permissions);
        }

        $user->load('permissions');

        return $this->successResponse($user);
    }

    public function getContractsUserModel($id)
    {
        $user = User::findOrFail($id);
        $documents = $user->documents()
            ->where('only_physical', 0)
            ->where('is_deleted', 0)
            ->get();

        return $this->successResponse($documents);
    }

    public function getContractsUserSigned($id)
    {
        $user = User::findOrFail($id);
        $documents = $user->documents()
            ->where('file_document', '!=', null)
            ->where('only_physical', 1)
            ->where('is_deleted', 0)
            ->with(['document' => function ($query) {
                $query->select('id', 'type', 'file');
            }])->get();

        return $this->successResponse($documents);
    }

    public function editUser(UserEditRequest $request, $id)
    {
        $user = User::findOrFail($id);

        $user->update($request->validated());

        $existingPositions = $user->positions->where("end_date", "==", null)->pluck('position_id')->toArray();

        $newPositions = array_values(array_diff($request->input('position_id', []), $existingPositions));

        $positionsToUpdate = array_values(array_diff($existingPositions, $request->input('position_id', [])));

        $user->positions()->whereIn('position_id', $positionsToUpdate)->update(['end_date' => now()]);

        foreach ($newPositions as $positionId) {

            if (!$user->positions->where("end_date", "===", null)->contains('position_id', $positionId)) {
                $user->positions()->create([
                    'position_id' => $positionId,
                    'start_date' => now(),
                ]);
            }
        }

        $existingTeams = $user->teams->where("is_deleted", 0)->pluck('team_id')->toArray();

        $newTeams = array_values(array_diff($request->input('team_id', []), $existingTeams));
        $teamsToUpdate = array_values(array_diff($existingTeams, $request->input('team_id', [])));

        // Mise à jour des équipes à mettre à jour avec is_deleted à 1
        $user->teams()->whereIn('team_id', $teamsToUpdate)->update(['is_deleted' => 1]);

        // Création de nouvelles équipes
        foreach ($newTeams as $teamId) {
            if (!$user->teams->where("is_deleted", 0)->contains('team_id', $teamId)) {
                $user->teams()->create([
                    'team_id' => $teamId,
                    'is_leader' => 0,
                    'integration_date' => now(),
                ]);
            }
        }

        return $this->successResponse($user->load('positions', 'teams'));
    }

    public function destroyUser($id)
    {
        // $this->authorize('update', User::class);
        $user = User::findOrFail($id);
        $user->delete();

        return $this->successResponse($user->load('positions', 'teams'));
    }

    public function getTeamsDepartment($id)
    {
        $teams = Team::where('status', 'active')->where('department_id', $id)->get();

        return $this->successResponse($teams);
    }

    public function archiveUser($id)
    {
        // $this->authorize('archive', User::class);
        $user = User::findOrFail($id);
        $user->update(['status' => 'inactive']);

        return $this->successResponse($user);
    }

    public function resetUser($id)
    {
        // $this->authorize('archive', User::class);
        $user = User::findOrFail($id);
        $user->update(['status' => 'active']);

        return $this->successResponse($user);
    }

    public function generatePDF($id, Request $request)
    {
        $document = Document::find($id); // Remplacez Document par le nom de votre modèle


        $userC = new UserDocument();
        //id doc chnager le nom de la colonne
        $userC->document_id = $id;
        $userC->user_id = $request->input('user_id');
        $userC->start_date = $request->input('start_date');
        $userC->end_date = $request->input('end_date');
        $userC->salary = $request->input('salary');
        $userC->status = "Draft";
        $userC->only_physical = 0;
        $date_now = Carbon::now()->toDateTimeString();
        $userC->date_status = $date_now;
        $userC->place_of_work = $request->input('place_of_work');
        $userC->start_time_work = $request->input('start_time_work');
        $userC->end_time_work = $request->input('end_time_work');
        $userC->trial_period = $request->input('trial_period');
        $userC->file = null;
        $userC->save();

        // Remplacez les variables par les informations de l'utilisateur
        $body = str_replace('$$last_name$$', $request->last_name, $document->body);
        $body = str_replace('$$first_name$$', $request->first_name, $body);
        $body = str_replace('$$email$$', $request->email, $body);

        $pdf = PDF::loadHTML($body);
        $filename = $request->type . '_'  . $request->last_name . '_' . $request->first_name . '.pdf';

        return $pdf->download($filename);
    }

    public function AffectContractsToUser(Request $request)
    {
        $userC = new UserDocument();
        //id doc chnager le nom de la colonne
        $userC->document_id = $id;
        $userC->user_id = $request->input('user_id');
        $userC->start_date = $request->input('start_date');
        $userC->end_date = $request->input('end_date');
        $userC->salary = $request->input('salary');
        $userC->status = "Draft";
        $userC->only_physical = 0;
        $date_now = Carbon::now()->toDateTimeString();
        $userC->date_status = $date_now;
        $userC->place_of_work = $request->input('place_of_work');
        $userC->start_time_work = $request->input('start_time_work');
        $userC->end_time_work = $request->input('end_time_work');
        $userC->trial_period = $request->input('trial_period');
        $userC->file = null;

        if ($userC->file != null) {
            $filename = public_path() . '/signed_document//' . $userC->file;
            if (File::exists($filename)) {
                File::delete($filename);
            }
        }

        if ($request->file('file')) {
            $name = $request->file('file')->getClientOriginalName();
            $path = $request->file('file')->move('signed_document', $name);
            $userC->file = $name;
        }

        $userC->save();

        $document = UserDocument::with(['user', 'document'])
            ->where('id', $userC->id)
            ->first();

        $download = $request->input('download');

        if ($download != null) {
            $user = $document->user;

            $body = str_replace(
                ['$$last_name$$', '$$first_name$$', '$$email$$', '$$sex$$', '$$date_birth$$', '$$place_birth$$', '$$cin$$', '$$delivery_date_cin$$', '$$delivery_place_cin$$', '$$integration_date$$' , '$$job_name$$'],
                [$user->last_name, $user->first_name, $user->email, $user->sex, $user->date_birth, $user->place_birth, $user->cin, $user->delivery_date_cin, $user->delivery_place_cin, $user->integration_date  , $user->positions[0]->position->job_name],
                $document->document->body
            );

            if ($download == "pdf") {
                $pdf = PDF::loadHTML($body);
                $filename = $document->document->documentType->name . '_'  . $user->last_name . '_' . $user->first_name . '.pdf';

                return $pdf->download($filename);
            } else if ($download == "word") {
                $phpWord = new PhpWord();

                // Ajouter le contenu HTML converti dans le document Word
                $section = $phpWord->addSection();
                \PhpOffice\PhpWord\Shared\Html::addHtml($section, $body);

                // Générer le nom de fichier
                $filename = $document->document->documentType->name . '_' . $user->last_name . '_' . $user->first_name . '.docx';

                // Enregistrer le document Word
                $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
                $objWriter->save($filename);

                return response()->download($filename)->deleteFileAfterSend(true);
            }
        }
    }

    public function editContractsToUser(Request $request, $id)
    {
        $userContract  = UserDocument::findOrFail($id);
    
        $userContract->document_id = $request->input('document_id');
        $userContract->user_id = $request->input('user_id');
        $userContract->start_date = $request->input('start_date');
        $userContract->end_date = $request->input('end_date');
        $userContract->salary = $request->input('salary');
        $userContract->status = "Draft";
        $userContract->only_physical = 0;
        $date_now = Carbon::now()->toDateTimeString();
        $userContract->date_status = $date_now;
        $userContract->place_of_work = $request->input('place_of_work');
        $userContract->start_time_work = $request->input('start_time_work');
        $userContract->end_time_work = $request->input('end_time_work');
        $userContract->trial_period = $request->input('trial_period');
        $userContract->file = null;

        // Supprimer l'ancien fichier s'il existe
        if ($userContract->file != null) {
            $filename = public_path() . '/signed_document//' . $userContract->file;
            if (File::exists($filename)) {
                File::delete($filename);
            }
        }

        // Enregistrer le nouveau fichier s'il est fourni
        if ($request->file('file')) {
            $name = $request->file('file')->getClientOriginalName();
            $path = $request->file('file')->move('signed_document', $name);
            $userContract->file = $name;
        }

        $userContract->save();

        $document = UserDocument::with(['user', 'document'])
            ->where('id', $userContract->id)
            ->first();

        $download = $request->input('download');

        if ($download != null) {
            $user = $document->user;

            $body = str_replace(
                ['$$last_name$$', '$$first_name$$', '$$email$$', '$$sex$$', '$$date_birth$$', '$$place_birth$$', '$$cin$$', '$$delivery_date_cin$$', '$$delivery_place_cin$$', '$$integration_date$$',  '$$job_name$$'],
                [$user->last_name, $user->first_name, $user->email, $user->sex, $user->date_birth, $user->place_birth, $user->cin, $user->delivery_date_cin, $user->delivery_place_cin, $user->integration_date , $user->positions[0]->position->job_name],
                $document->document->body
            );

            if ($download == "pdf") {
                $pdf = PDF::loadHTML($body);
                $filename = $document->document->documentType->name . '_'  . $user->last_name . '_' . $user->first_name . '.pdf';
                return $pdf->download($filename);

            } else if ($download == "word") {
                $phpWord = new PhpWord();

                // Ajouter le contenu HTML converti dans le document Word
                $section = $phpWord->addSection();
                \PhpOffice\PhpWord\Shared\Html::addHtml($section, $body);

                // Générer le nom de fichier
                $filename = $document->document->documentType->name . '_' . $user->last_name . '_' . $user->first_name . '.docx';

                // Enregistrer le document Word
                $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
                $objWriter->save($filename);

                // Télécharger le fichier
                return response()->download($filename)->deleteFileAfterSend(true);
            }
        }
    }

    public function editUserIntern(EditUserInternRequest $request, $id)
    {
        $user = User::findOrFail($id);
        $user->last_name = $request->last_name;
        $user->first_name = $request->first_name;
        $user->phone = $request->phone;
        $user->address = $request->address;
        $user->family_situation = $request->family_situation;
        $user->nb_children = $request->nb_children;
        $user->level_studies = $request->level_studies;
        $user->delivery_date_cin = $request->delivery_date_cin;
        $user->save();

        return $this->successResponse($user);
    }

    public function ChangePhotoProfil(Request $request, $id)
    {
        $extension = explode('/', explode(':', substr($request->input('base64string'), 0, strpos($request->input('base64string'), ';')))[1])[1];
        $replace = substr($request->input('base64string'), 0, strpos($request->input('base64string'), ',') + 1);
        $file = str_replace($replace, '', $request->input('base64string'));
        $decodedFile = str_replace(' ', '+', $file);
        $path =  Str::random(5) . time() . '.' . $extension;

        Storage::disk('public')->put("photo/" . $path, base64_decode($decodedFile));

        $user = user::findOrFail($id);
        $user->image = $path;
        $user->save();

        return [
            'user' => $user,
        ];
    }

    public function getPositionUser($id_pos)
    {
        $userIds = PositionUser::where('position_id', $id_pos)->pluck('user_id');

        $users = User::whereIn('id', $userIds)
            ->select('id', 'first_name', 'last_name')
            ->get();

        return response()->json($users);
    }

    public function uploadOldContract(OldContractRequest $request, $id_user)
    {
        $name = "";

        if ($request->file('file')) {
            $name = explode(".", $request->file('file')->getClientOriginalName())[0] . "_" . Str::random(4) . "." . explode(".", $request->file('file')->getClientOriginalName())[1];
            $path = $request->file('file')->move('old_document', $name);
        }

        $user_document = new UserDocument();

        if ($name != "") {
            $user_document->file_document = $name;
        }

        $user_document->file_document = $name;
        $user_document->document_id = $request->input('document_id');
        $user_document->start_date = $request->input('start_date');
        $user_document->status = "Signed";
        $user_document->date_status = now();
        $user_document->is_deleted = 0;
        $user_document->only_physical = 1;
        $user_document->end_date = $request->input('end_date');
        $user_document->user_id = $id_user;
        $user_document->save();

        return $this->successResponse($user_document);
    }

    // DownloadSignedContract and DownloadOldContract 
    function downloadOldContract($id_user_document)
    {
        $user_document = UserDocument::findOrFail($id_user_document);
        $file_name_1 = "old_document/" . $user_document->file;
        $file_name_2 = "signed_document/" . $user_document->file;
        $path_1 = public_path($file_name_1);
        $path_2 = public_path($file_name_2);

        if (file_exists($path_1)) {
            return response()->download($path_1);
        } elseif (file_exists($path_2)) {
            return response()->download($path_2);
        }
    }

    public function downloadModelContracts($id_user_document)
    {
        $document = UserDocument::with(['user', 'document'])
            ->where([
                ['id', '=', $id_user_document],
            ])->firstOrFail();

        $user = $document->user;

        $body = str_replace('$$last_name$$', $user->last_name, $document->document->body);
        $body = str_replace('$$first_name$$', $user->first_name, $body);
        $body = str_replace('$$email$$', $user->email, $body);
        $body = str_replace('$$sex$$', $user->sex, $body);
        $body = str_replace('$$date_birth$$', $user->date_birth, $body);
        $body = str_replace('$$place_birth$$', $user->place_birth, $body);
        $body = str_replace('$$cin$$', $user->cin, $body);
        $body = str_replace('$$delivery_date_cin$$', $user->delivery_date_cin, $body);
        $body = str_replace('$$delivery_place_cin$$', $user->delivery_place_cin, $body);
        $body = str_replace('$$integration_date$$', $user->integration_date, $body);
        $body = str_replace('$$delivery_date_cin$$', $user->delivery_date_cin, $body);
        $body = str_replace('$$delivery_place_cin$$', $user->delivery_place_cin, $body);


        $pdf = PDF::loadHTML($body);
        $filename = $document->document->documentType->name . '_'  . $user->last_name . '_' . $user->first_name . '.pdf';

        return $pdf->download($filename);
    }

    public function getAllContractsUser(Request $request, $id)
    {
        $documentTypeName = $request->input('document_type_name');

        $document = Document::whereHas('documentType', function ($query) use ($documentTypeName) {
            $query->where('name', $documentTypeName)->where('status', 'active');
        })
            ->where('status', 'active')
            ->latest()
            ->firstOrFail();

        $documents = UserDocument::where('user_id', $id)->where('document_id', $document->id)->get();

        return $this->successResponse($documents);
    }

    public function checkUserCin(Request $request)
    {
        $cin = $request->input('cin');
        $cinExiste = User::where('cin', $cin)->exists();
   
        return $this->successResponse( $cinExiste);
    }

    public function checkUserPassport(Request $request)
    {
        $num_passport = $request->input('num_passport');
        $passportExiste = User::where('num_passport', $num_passport)->whereNotNull('num_passport')->exists();
   
        return $this->successResponse( $passportExiste);
    }

    public function checkUserEmail(Request $request)
    {
        $email = $request->input('email');
        $emailExiste = User::where('email', $email)->exists();
   
        return $this->successResponse($emailExiste);
    }

    public function checkUserEmailProf(Request $request)
    {
        $email_prof = $request->input('email_prof');
        $emailProfExiste = User::where('email_prof', $email_prof)->exists();
   
        return $this->successResponse($emailProfExiste);
    }

    public function checkUserPhone(Request $request)
    {
        $phone = $request->input('phone');
        $phoneExiste = User::where('phone', $phone)->exists();
   
        return $this->successResponse($phoneExiste);
    }

    public function checkUserPhoneEmergency(Request $request)
    {
        $phone_emergency = $request->input('phone_emergency');
        $phoneEmergencyExiste = User::where('phone_emergency', $phone_emergency)->exists();
   
        return $this->successResponse($phoneEmergencyExiste);
    }
    

}
