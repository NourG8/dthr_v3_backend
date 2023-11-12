<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Requests\Company\AddCompanyRequest;
use App\Http\Requests\Company\EditCompanyRequest;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class CompanyController extends Controller
{
    static public function getCompany()
    {
        $companies = Company::all();
        return $this->successResponse( $companies );
    }

    public function AddCompany(AddCompanyRequest $request)
    {
        $company = Company::create($request->validated());

        return $this->successResponse($company);
    }

    public function editCompany(EditCompanyRequest $request, $id)
    {
        $company = Company::findOrFail($id);

        $validatedData = $request->except(['tableau_get', 'tableau_add']);
        $company->update($validatedData);

        $tableau_get = $request->input('tableau_get', []);
        $tableau_add = $request->input('tableau_add', []);
        $regimeSocial = array_merge($tableau_get, $tableau_add);
        
        // Assignez la valeur à la colonne 'regimeSocial'
        $company->regime_social = $regimeSocial;
        $company->save();

        return $this->successResponse($company);
    }

    public function ChangePhoto(Request $request,$id)
    {
        $extension = explode('/', explode(':', substr($request->input('base64string'), 0, strpos($request->input('base64string'), ';')))[1])[1];
        $replace = substr($request->input('base64string'), 0, strpos($request->input('base64string'), ',')+1);
        $file = str_replace($replace, '', $request->input('base64string'));
        $decodedFile = str_replace(' ', '+', $file);
        $name =  Str::random(5) . time() .'.'. $extension;

        Storage::disk('public')->put("logo/".$name, base64_decode($decodedFile));

        $company = Company::findOrFail($id);
        $company->logo = $name;
        $company->save();

        return $this->successResponse($company);
    }

    public function AddRegimeSocial(Request $request)
    {
        $company = new Company();
        $company->regimeSocial = $request->input('regimeSocial');
        $company->save();

        return $this->successResponse($company);
    }

}
