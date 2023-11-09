<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserEditRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
          $id = $this->route('id'); // Obtenez l'id de la route
        //   dd($id);
        return [
            'last_name' => 'required|string',
            'first_name' => 'required|string',
            'image' => 'nullable',
            'sex' => ['required', Rule::in(['Male', 'Female', 'Other'])],
            'email' => ['required', 'email', Rule::unique('users')->ignore($id)],
            'email_prof' => ['nullable', 'email', Rule::unique('users')->ignore($id)],
            'address' => 'required|string',
            'date_birth' => 'required|date',
            'place_birth' => 'required|string',
            'status' => 'string',
            'nationality' => 'required|string',
            'phone' => 'required|string',
            'phone_emergency' => 'nullable|string',
            'Family_situation' => ['required', Rule::in(['Single', 'Married', 'Divorce', 'Widow'])],
            'nb_children' => 'required|integer',
            'level_studies' => 'required|string',
            'specialty' => 'required|string',
            'sivp' => ['required', Rule::in(['Yes', 'No'])],
            'registration' => 'required|string',
            'carte_id' => 'nullable|string',
            'duration_sivp' => 'nullable|string',
            'cin' => ['nullable', 'integer', Rule::unique('users')->ignore($id)],
            'num_passport' => ['nullable', 'string', Rule::unique('users')->ignore($id)],
            'delivery_date_cin' => 'nullable|date',
            'delivery_place_cin' => 'nullable|string',
            'integration_date' => 'required|date',
            'motivation' => 'array',
            'regime_social' => 'required|string',
            'text' => 'nullable|string',
            // 'department_id' => 'required|integer',
            'position_id' => 'required|integer',
            'team_id' => 'required|integer',
        ];
    }
}
