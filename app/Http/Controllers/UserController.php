<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PositionUser;
use App\Models\TeamUser;
use App\Models\Team;
use App\Models\Position;
use App\Models\UserContract;
use App\Http\Requests\Users\UserRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use DB;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use PDF;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

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
        ->whereHas('positions', function($q) {
            $q->whereHas('position', function($query) {
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
        ->whereHas('positions', function($query) {
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
        ]);

        $user->teams()->create([
            'team_id' => $request->input('team_id'),
            'is_leader' => 0,
            'integration_date' => now(),
        ]);

        $user->load('positions', 'teams');

        return $this->successResponse($user , 201);
    }


    public function DeleteContractsUser($id)
    {
        $userContract = UserContract::findOrFail($id);
        $userContract->update(['is_deleted' => 1]);

        $this->successResponse($userContract);
    }

    public function getContractsUserModel($id)
    {
        $users = DB::table('user_contracts')
        // ->leftJoin('user_contracts', 'user_contracts.user_id', '=', 'users.id')
        ->leftJoin('contracts', 'user_contracts.contract_id', '=', 'contracts.id')
        ->select('user_contracts.*','contracts.type','contracts.file')
        ->where([['user_contracts.user_id', '=', $id],
        ['user_contracts.OnlyPhysical', '=', 0],
        ['user_contracts.is_deleted', '=', 0]])
        ->get();
        return response()->json($users);
    }

    // public function getContractsUserSigned($id)
    // {
    //     $users = DB::table('user_contracts')
    //     // ->leftJoin('user_contracts', 'user_contracts.user_id', '=', 'users.id')
    //     ->leftJoin('contracts', 'user_contracts.contract_id', '=', 'contracts.id')
    //     ->select('user_contracts.*','contracts.type','contracts.file')
    //     ->where([['user_contracts.user_id', '=', $id],
    //             ['user_contracts.fileContract', '!=', null],
    //             ['user_contracts.OnlyPhysical', '=', 1],
    //             ['user_contracts.is_deleted', '=', 0]])
    //     ->get();
    //     return response()->json($users);
    // }

    // public function editUser(Request $request,$id)
    // {
    //     $this->authorize('update', User::class);
    //     $user = User::findOrFail($id);
    //     $user->lastName= $request->lastName;
    //     $user->firstName= $request->firstName;
    //     $user->sex= $request->sex;
    //     $user->email = $request->email;
    //     $user->emailProf = $request->emailProf;
    //     $user->address = $request->address;
    //     $user->dateBirth =$request->dateBirth;
    //     $user->placeBirth =$request->placeBirth;
    //     $user->nationality =$request->nationality;
    //     $user->phone = $request->phone;
    //     $user->pwd_reset_admin = 0;
    //     $user->phoneEmergency = $request->phoneEmergency;
    //     $user->FamilySituation = $request->FamilySituation;
    //     $user->nbChildren = $request->nbChildren;
    //     $user->levelStudies = $request->levelStudies;
    //     $user->specialty = $request->specialty;
    //     $user->matricule = $request->matricule;
    //     $user->carteId = $request->carteId;
    //     // $user->sivp = $request->sivp;
    //     // $user->durationSivp = $request->durationSivp;
    //     $user->cin = $request->cin;
    //     $user->deliveryDateCin = $request->deliveryDateCin;
    //     $user->deliveryPlaceCin = $request->deliveryPlaceCin;
    //     $user->numPassport = $request->numPassport;
    //     $user->motivation = $request->motivation;
    //     $user->integrationDate = $request->integrationDate;
    //     $password = Str::random(8);
    //     $user->password = Hash::make($password);
    //     $user->regimeSocial =  $request->regimeSocial;
    //     $user->text = $request->text;
    //     $user->save();

    //     $user->department_id = $request->department_id;
    //     $user->position_id = $request->position_id;
    //     $user->team_id =  $request->team_id;

    //     $team_user = TeamUser::where([
    //         ['user_id',$user->id],
    //      ])->get();
    //     $team_user[0]->team_id= $user->team_id ;
    //     $team_user[0]->save();

    //     $position_user = PositionUser::where([
    //                                             ['user_id',$user->id],
    //                                             ['endDate', "=", null]
    //                                         ])->get();
    //     if($position_user[0]->position_id != $request->input('position_id')){
    //         $position_user[0]->endDate = now();
    //         $position_user[0]->save();

    //         $new_position_user = new PositionUser();
    //         $new_position_user->position_id = $request->input('position_id');
    //         $new_position_user->startDate = now();
    //         $new_position_user->user_id = $user->id;
    //         $new_position_user->save();
    //     }
    //     return response()->json([
    //         'user' => $user,
    //         'success' => true
    //     ], 200);
    // }

    // public function destroyUser($id)
    // {
    //     $this->authorize('update', User::class);
    //     $u = User::findOrFail($id);
    //     $u->is_deleted = 1;
    //     $u->save();
    //     return response()->json([
    //         'user' => $u,
    //         'success' => true
    //     ], 200);
    // }

    // public function getTeams_Department($id_dep)
    // {
    //     $teams = Team::where([
    //         ['department_id',$id_dep], ['status','active']
    //     ])->get();
    //     return response()->json([
    //         'teams' => $teams,
    //         'success' => true
    //     ], 200);
    // }

    // public function archiveUser($id)
    // {
    //     // $this->authorize('archive', User::class);
    //     $user = User::findOrFail($id);
    //     $user->status = "inactive";
    //     $user->save();
    //     return response()->json([
    //         'user' => $user,
    //         'success' => true
    //     ], 200);
    // }

    // public function resetUser($id)
    // {
    //     // $this->authorize('archive', User::class);
    //     $user = User::findOrFail($id);
    //     $user->status = "active";
    //     $user->save();
    //     return response()->json([
    //         'user' => $user,
    //         'success' => true
    //     ], 200);
    // }

    // public function AffectContractsToUser(Request $request)
    // {
    //     $userC = new UserContract();
    //     $userC->contract_id = $request->input('contract_id');
    //     $userC->user_id = $request->input('user_id');
    //     $userC->startDate = $request->input('startDate');
    //     $userC->endDate = $request->input('endDate');
    //     $userC->salary = $request->input('salary');
    //     $userC->is_deleted = 0;
    //     $userC->status = "Draft";
    //     $userC->OnlyPhysical = 0;
    //     $date_now = Carbon::now()->toDateTimeString();
    //     $userC->date_status = $date_now;
    //     $userC->placeOfWork = $request->input('placeOfWork');
    //     $userC->placeOfWork = $request->input('placeOfWork');
    //     $userC->startTimeWork = $request->input('startTimeWork');
    //     $userC->endTimeWork = $request->input('endTimeWork');
    //     $userC->trialPeriod = $request->input('trialPeriod');
    //     $userC->fileContract = null;
    //     $userC->save();

    //     $contract_result = DB::table('user_contracts')
    //     ->leftJoin('users', 'user_contracts.user_id', '=', 'users.id')
    //     ->leftJoin('contracts', 'user_contracts.contract_id', '=', 'contracts.id')
    //     ->select('user_contracts.*','contracts.type','contracts.file','users.lastname','users.firstname')
    //     ->where([['user_contracts.id', '=', $userC->id],
    //              ['user_contracts.is_deleted', '=', 0]])
    //     ->get();

    //     $contract = json_decode($contract_result, true);

    //     $download = $request->input('download');

    //     if($download != null){
    //             $user_result = DB::table('users')
    //             ->leftJoin('position_users', 'position_users.user_id', '=', 'users.id')
    //             ->leftJoin('positions', 'position_users.position_id', '=', 'positions.id')
    //             ->select('users.*','positions.jobName')
    //             ->where('users.id', '=', $contract[0]['user_id'])
    //             ->get();

    //             $user = json_decode($user_result, true);
    //             $file_name = $contract[0]['file'];
    //             $type = $contract[0]['type'];
    //             $name = $user[0]['lastName'] ."_" .$user[0]['firstName'] ;

    //             $template = "contract\\" .$file_name;
    //             $templateProcessor = new TemplateProcessor($template);
    //             $templateProcessor->setValue('firstName', $user[0]['firstName']);
    //             $user_sex = '';
    //             if($user[0]['sex'] == 'Women'){
    //                 if($user[0]['FamilySituation'] == 'Single'){
    //                     $user_sex = "Mlle";
    //                 }else{
    //                     $user_sex = "Mme";
    //                 }
    //             }else if($user[0]['sex'] == 'Man'){
    //                 $user_sex = "Mr";
    //             }
    //             $templateProcessor->setValue('sex', $user_sex);
    //             $templateProcessor->setValue('lastName', $user[0]['lastName']);
    //             $templateProcessor->setValue('dateBirth', $user[0]['dateBirth']);
    //             $templateProcessor->setValue('placeBirth', $user[0]['placeBirth']);
    //             $templateProcessor->setValue('cin', $user[0]['cin']);
    //             $templateProcessor->setValue('deliveryDateCin', $user[0]['deliveryDateCin']);
    //             $templateProcessor->setValue('deliveryPlaceCin', $user[0]['deliveryPlaceCin']);
    //             $templateProcessor->setValue('integrationDate', $user[0]['integrationDate']);
    //             $templateProcessor->setValue('position', $user[0]['jobName']);
    //             $templateProcessor->setValue('salary', $contract[0]['salary']);

    //             $integration_date = $user[0]['integrationDate'];
    //             $day = date('d', strtotime($integration_date));
    //             if($day == 1){
    //                 $date_ticket = date('Y-m-d',strtotime('+2 month',strtotime($integration_date)));
    //             }else{
    //                 $date_ticket = date('Y-m-d',strtotime('+3 month',strtotime($integration_date)));
    //             }

    //             $templateProcessor->setValue('dateTicket', $date_ticket);

    //             $date_now = Carbon::now()->toDateTimeString();
    //             $templateProcessor->setValue('date_now', $date_now);
    //             $new_file_name = "contract\\contract_" .$type . "_" .$name .".docx";

    //             if($download == "pdf"){
    //                 $domPdfPath = base_path('vendor/dompdf/dompdf');
    //                 \PhpOffice\PhpWord\Settings::setPdfRendererPath($domPdfPath);
    //                 \PhpOffice\PhpWord\Settings::setPdfRendererName('DomPDF');

    //                 /*@ Save Temporary Word File With New Name */
    //                 $saveDocPath = public_path($new_file_name);
    //                 $templateProcessor->saveAs($saveDocPath);

    //                 // Load temporarily create word file
    //                 $Content = \PhpOffice\PhpWord\IOFactory::load($saveDocPath);

    //                 //Save it into PDF
    //                 $PDFWriter = \PhpOffice\PhpWord\IOFactory::createWriter($Content,'PDF');

    //                 $new_file_name_pdf = "contract\\contract_" .$type . "_" .$name .".pdf";
    //                 $PDFWriter->save(public_path($new_file_name_pdf));

    //                 //delete word!!
    //                 File::delete($saveDocPath);

    //                 return response()->download($new_file_name_pdf)->deleteFileAfterSend(true);

    //             }else if($download == "word"){
    //                 //Save word code!!
    //                 $new_file_name = "contract\\contract_" .$type . "_" .$name .".docx";
    //                 $templateProcessor->saveAs($new_file_name);

    //                 return response()->download($new_file_name)->deleteFileAfterSend(true);
    //             }
    //         }
    //     // return response()->json([
    //     //     'user_Contracts' => $contract[0],
    //     //     'success' => true
    //     // ], 200);
    // }

    // public function EditContractsToUser(Request $request,$id)
    // {
    //     $userC = UserContract::findOrFail($id);
    //     $userC->contract_id = $request->input('contract_id');
    //     $userC->user_id = $request->input('user_id');
    //     $userC->startDate = $request->input('startDate');
    //     $userC->endDate = $request->input('endDate');
    //     $userC->salary = $request->input('salary');
    //     $userC->raison = $request->input('raison');
    //     $userC->is_deleted = 0;
    //     $userC->OnlyPhysical = 0;
    //     $userC->status = $request->input('status');
    //     $userC->date_status = $request->input('date_status');
    //     $userC->placeOfWork = $request->input('placeOfWork');
    //     $userC->startTimeWork = $request->input('startTimeWork');
    //     $userC->endTimeWork = $request->input('endTimeWork');
    //     $userC->trialPeriod = $request->input('trialPeriod');

    //     if($userC->fileContract != null){
    //       $filename = public_path().'/signed_contract//'.$userC->fileContract;
    //         if (File::exists($filename)) {
    //             File::delete($filename);
    //         }
    //     }

    //     if($request->file('file')){
    //         $name = $request->file('file')->getClientOriginalName();
    //         $path = $request->file('file')->move('signed_contract',$name);
    //         $userC->fileContract = $name;
    //     }

    //     $userC->save();

    //     $contract_result = DB::table('user_contracts')
    //         ->leftJoin('users', 'user_contracts.user_id', '=', 'users.id')
    //         ->leftJoin('contracts', 'user_contracts.contract_id', '=', 'contracts.id')
    //         ->select('user_contracts.*','contracts.type','contracts.file','users.lastname','users.firstname')
    //         ->where([['user_contracts.id', '=', $userC->id],
    //                 ['user_contracts.is_deleted', '=', 0]])
    //         ->get();

    //     $contract = json_decode($contract_result, true);

    //     $download = $request->input('download');

    //     if($download != null){
    //             $user_result = DB::table('users')
    //             ->leftJoin('position_users', 'position_users.user_id', '=', 'users.id')
    //             ->leftJoin('positions', 'position_users.position_id', '=', 'positions.id')
    //             ->select('users.*','positions.jobName')
    //             ->where('users.id', '=', $contract[0]['user_id'])
    //             ->get();

    //             $user = json_decode($user_result, true);
    //             $file_name = $contract[0]['file'];
    //             $type = $contract[0]['type'];
    //             $name = $user[0]['lastName'] ."_" .$user[0]['firstName'] ;


    //             $template = "contract\\" .$file_name;
    //             $templateProcessor = new TemplateProcessor($template);
    //             $templateProcessor->setValue('firstName', $user[0]['firstName']);
    //             $user_sex = '';
    //             if($user[0]['sex'] == 'Women'){
    //                 if($user[0]['FamilySituation'] == 'Single'){
    //                     $user_sex = "Mlle";
    //                 }else{
    //                     $user_sex = "Mme";
    //                 }
    //             }else if($user[0]['sex'] == 'Man'){
    //                 $user_sex = "Mr";
    //             }
    //             $templateProcessor->setValue('sex', $user_sex);
    //             $templateProcessor->setValue('lastName', $user[0]['lastName']);
    //             $templateProcessor->setValue('dateBirth', $user[0]['dateBirth']);
    //             $templateProcessor->setValue('placeBirth', $user[0]['placeBirth']);
    //             $templateProcessor->setValue('cin', $user[0]['cin']);
    //             $templateProcessor->setValue('deliveryDateCin', $user[0]['deliveryDateCin']);
    //             $templateProcessor->setValue('deliveryPlaceCin', $user[0]['deliveryPlaceCin']);
    //             $templateProcessor->setValue('integrationDate', $user[0]['integrationDate']);
    //             $templateProcessor->setValue('position', $user[0]['jobName']);
    //             $templateProcessor->setValue('salary', $contract[0]['salary']);

    //             $integration_date = $user[0]['integrationDate'];
    //             $day = date('d', strtotime($integration_date));
    //             if($day == 1){
    //                 $date_ticket = date('Y-m-d',strtotime('+2 month',strtotime($integration_date)));
    //             }else{
    //                 $date_ticket = date('Y-m-d',strtotime('+3 month',strtotime($integration_date)));
    //             }

    //             $templateProcessor->setValue('dateTicket', $date_ticket);

    //             $date_now = Carbon::now()->toDateTimeString();
    //             $templateProcessor->setValue('date_now', $date_now);
    //             $new_file_name = "contract\\contract_" .$type . "_" .$name .".docx";

    //             if($download == "pdf"){
    //                 $domPdfPath = base_path('vendor/dompdf/dompdf');
    //                 \PhpOffice\PhpWord\Settings::setPdfRendererPath($domPdfPath);
    //                 \PhpOffice\PhpWord\Settings::setPdfRendererName('DomPDF');

    //                 /*@ Save Temporary Word File With New Name */
    //                 $saveDocPath = public_path($new_file_name);
    //                 $templateProcessor->saveAs($saveDocPath);

    //                 // Load temporarily create word file
    //                 $Content = \PhpOffice\PhpWord\IOFactory::load($saveDocPath);

    //                 //Save it into PDF
    //                 $PDFWriter = \PhpOffice\PhpWord\IOFactory::createWriter($Content,'PDF');

    //                 $new_file_name_pdf = "contract\\contract_" .$type . "_" .$name .".pdf";
    //                 $PDFWriter->save(public_path($new_file_name_pdf));

    //                 //delete word!!
    //                 File::delete($saveDocPath);

    //                 return response()->download($new_file_name_pdf)->deleteFileAfterSend(true);

    //             }else if($download == "word"){
    //                 //Save word code!!
    //                 $new_file_name = "contract\\contract_" .$type . "_" .$name .".docx";
    //                 $templateProcessor->saveAs($new_file_name);

    //                 return response()->download($new_file_name)->deleteFileAfterSend(true);
    //             }
    //         }
    //     // return response()->json([
    //     //     'user_Contracts' => $contract[0],
    //     //     'success' => true
    //     // ], 200);
    // }

    // public function editUserIntern(Request $request,$id)
    // {
    //     $user = User::findOrFail($id);
    //     $user->lastName= $request->lastName;
    //     $user->firstName= $request->firstName;
    //     $user->phone = $request->phone;
    //     $user->address = $request->address;
    //     $user->FamilySituation = $request->FamilySituation;
    //     $user->nbChildren = $request->nbChildren;
    //     $user->levelStudies = $request->levelStudies;
    //     $user->deliveryDateCin = $request->deliveryDateCin;
    //     $user->save();
    //     return response()->json([
    //         'user' => $user,
    //         'success' => true
    //     ], 200);
    // }

    // public function ChangePhotoProfil(Request $request,$id)
    // {
    //     $extension = explode('/', explode(':', substr($request->input('base64string'), 0, strpos($request->input('base64string'), ';')))[1])[1];
    //     $replace = substr($request->input('base64string'), 0, strpos($request->input('base64string'), ',')+1);
    //     $file = str_replace($replace, '', $request->input('base64string'));
    //     $decodedFile = str_replace(' ', '+', $file);
    //     $path =  Str::random(5) . time() .'.'. $extension;

    //     Storage::disk('public')->put("photo/".$path, base64_decode($decodedFile));

    //     $user = user::findOrFail($id);
    //     $user->image = $path;
    //     $user->save();

    //     return [
    //         'user'=> $user,
    //     ];
    // }

    // public function getPositionUser($id_pos)
    // {
    //     $user = DB::table('position_users')
    //     ->leftJoin('positions', 'position_users.position_id', '=', 'positions.id')
    //     ->leftJoin('users', 'position_users.user_id', '=', 'users.id')
    //     ->select('users.id','users.firstName','users.lastName')
    //     ->where('position_id',$id_pos)
    //     ->get();
    //      return response()->json($user);
    // }

    // private function ValidationData(){
    //     return [
    //         'name' => ['required', 'unique:users', 'max:255'],
    //         'email' => ['required', 'unique:users', 'max:255'],
    //         'password' =>'required',
    //     ];
    // }

    // function uploadOldContract(Request $request,$id_user){
    //     // 1er methode to upload file
    //     $validatedData = $request->validate([
    //         'file' => 'required',
    //        ]);

    //        $name = "";
    //        $end_date = null;

    //     if($request->input('endDate')){
    //         $end_date = $request->input('endDate');
    //     }

    //     if($request->file('file')){
    //         // explode(", ", $string);
    //          $name = explode(".",$request->file('file')->getClientOriginalName())[0] ."_". Str::random(4) .".". explode(".",$request->file('file')->getClientOriginalName())[1];
    //          $path = $request->file('file')->move('old_contract',$name);
    //     }

    //     $user_contract = new UserContract();

    //     if($name != ""){
    //         $user_contract->fileContract = $name;
    //     }

    //     $user_contract->contract_id = $request->input('contract_id');
    //     $user_contract->startDate = $request->input('startDate');
    //     $user_contract->status = "Signed";
    //     $date_now = Carbon::now()->toDateTimeString();
    //     $user_contract->date_status = $date_now;
    //     $user_contract->is_deleted = 0;
    //     $user_contract->OnlyPhysical =1;
    //     $user_contract->endDate = $end_date;
    //     $user_contract->user_id = $id_user;
    //     $user_contract->save();

    //     return response()->json([
    //         'user_contract' => $user_contract,
    //         'success' => true
    //     ], 200);
    // }

    // function DownloadOldContract($id_user_contract){
    //     // 1er methode to download file
    //     $user_contract = UserContract::findOrFail($id_user_contract);
    //     $file_name_1 = "old_contract\\" .$user_contract->fileContract;
    //     $file_name_2 = "signed_contract\\" .$user_contract->fileContract;
    //     $path_1 =  public_path($file_name_1);
    //     $path_2 =  public_path($file_name_2);
    //     if (file_exists($path_1)){
    //         return response()->download($path_1);
    //     }
    //     if (file_exists($path_2)){
    //         return response()->download($path_2);
    //     }
    //     }

    // // function DownloadSignedContract($id_user_contract){
    // //     // 1er methode to download file
    // //     $user_contract = UserContract::findOrFail($id_user_contract);
    // //     $file_name = "signed_contract\\" .$user_contract->fileContract;
    // //     $path =  public_path($file_name);
    // //     return response()->download($path);
    // // }

    // public function DownloadModelContracts($id_user_contract)
    // {
    //     $contract_result = DB::table('user_contracts')
    //     ->leftJoin('users', 'user_contracts.user_id', '=', 'users.id')
    //     ->leftJoin('contracts', 'user_contracts.contract_id', '=', 'contracts.id')
    //     ->select('user_contracts.*','contracts.type','contracts.file','users.lastname','users.firstname')
    //     ->where([['user_contracts.id', '=', $id_user_contract],
    //              ['user_contracts.is_deleted', '=', 0]])
    //     ->get();

    //     $contract = json_decode($contract_result, true);

    //     if($contract){
    //         $user_result = DB::table('users')
    //             ->leftJoin('position_users', 'position_users.user_id', '=', 'users.id')
    //             ->leftJoin('positions', 'position_users.position_id', '=', 'positions.id')
    //             ->select('users.*','positions.jobName')
    //             ->where('users.id', '=', $contract[0]['user_id'])
    //             ->get();
    //                 $user = json_decode($user_result, true);

    //             $file_name = $contract[0]['file'];
    //             $type = $contract[0]['type'];
    //             $name = $user[0]['lastName'] ."_" .$user[0]['firstName'] ;

    //             $template = "contract\\" .$file_name;
    //             $templateProcessor = new TemplateProcessor($template);
    //             $templateProcessor->setValue('firstName', $user[0]['firstName']);
    //             $user_sex = '';
    //             if($user[0]['sex'] == 'Women'){
    //                 if($user[0]['FamilySituation'] == 'Single'){
    //                     $user_sex = "Mlle";
    //                 }else{
    //                     $user_sex = "Mme";
    //                 }
    //             }else if($user[0]['sex'] == 'Man'){
    //                 $user_sex = "Mr";
    //             }
    //             $templateProcessor->setValue('sex', $user_sex);
    //             $templateProcessor->setValue('lastName', $user[0]['lastName']);
    //             $templateProcessor->setValue('dateBirth', $user[0]['dateBirth']);
    //             $templateProcessor->setValue('placeBirth', $user[0]['placeBirth']);
    //             $templateProcessor->setValue('cin', $user[0]['cin']);
    //             $templateProcessor->setValue('deliveryDateCin', $user[0]['deliveryDateCin']);
    //             $templateProcessor->setValue('deliveryPlaceCin', $user[0]['deliveryPlaceCin']);
    //             $templateProcessor->setValue('integrationDate', $user[0]['integrationDate']);
    //             $templateProcessor->setValue('position', $user[0]['jobName']);
    //             $templateProcessor->setValue('salary', $contract[0]['salary']);

    //             $integration_date = $user[0]['integrationDate'];
    //             $day = date('d', strtotime($integration_date));
    //             if($day == 1){
    //                 $date_ticket = date('Y-m-d',strtotime('+2 month',strtotime($integration_date)));
    //             }else{
    //                 $date_ticket = date('Y-m-d',strtotime('+3 month',strtotime($integration_date)));
    //             }

    //             $templateProcessor->setValue('dateTicket', $date_ticket);

    //             $date_now = Carbon::now()->toDateTimeString();
    //             $templateProcessor->setValue('date_now', $date_now);

    //             $new_file_name = "contract\\contract_" .$type . "_" .$name .".docx";
    //             $templateProcessor->saveAs($new_file_name);

    //                 return response()->download($new_file_name)->deleteFileAfterSend(true);
    //           }
    // }

    // public function getRole_Auth($id){
    //     $role_user = DB::table('users')
    //     ->leftJoin('position_users', 'position_users.user_id', '=', 'users.id')
    //     ->leftJoin('positions', 'position_users.position_id', '=', 'positions.id')
    //     ->leftJoin('roles', 'roles.id', '=', 'positions.role_id')
    //     ->select('roles.role')
    //     ->where('users.id','=',$id)
    //     ->get();
    //     return response()->json($role_user);
    // }

    // public function getAllContractsUser($id)
    // {
    //     $contract = DB::table('user_contracts')
    //         ->leftJoin('contracts', 'user_contracts.contract_id', '=', 'contracts.id')
    //         ->select('user_contracts.*','contracts.type')
    //         ->where([['user_contracts.user_id', '=', $id],
    //         ['user_contracts.is_deleted', '=', 0]])
    //         ->get();

    //     return response()->json([
    //         'contract' => $contract,
    //         'success' => true
    //     ], 200);
    // }

}

