<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class AddCompanyRequest  extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string',
            'country' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
            'creation_date' => 'required|date',
            'logo' => 'required|string',
            'description' => 'required|string',
            'min_cin' => 'required|integer',
            'max_cin' => 'required|integer',
            'min_passport' => 'required|integer',
            'max_passport' => 'required|integer',
            'nationality' => 'required|string',
            // 'regimeSocial' => 'string',
            'first_color' => 'required|string',
            'second_color' => 'required|string',
            'type' => 'required|string',
            'type_telework' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'max_telework' => 'required|integer',
        ];
    }
}
