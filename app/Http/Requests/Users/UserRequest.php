<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
        return [
            'last_name' => 'required|string',
            'first_name' => 'required|string',
            'image' => 'nullable',
            'sex' => ['required', Rule::in(['Male', 'Female', 'Other'])],
            'email' => 'email|unique:users',
            'email_prof' => 'nullable|email|unique:users',
            'address' => 'required|string',
            'date_birth' => 'required|date',
            'place_birth' => 'required|string',
            'status' => 'string',
            'nationality' => 'required|string',
            'phone' => 'required|string',
            'phone_emergency' => 'nullable|string',
            'family_situation' => ['required', Rule::in(['Single', 'Married', 'Divorce', 'Widow'])],
            'nb_children' => 'required|integer',
            'level_studies' => 'required|string',
            'specialty' => 'required|string',
            'sivp' => ['nullable', Rule::in(['Yes', 'No'])],
            'registration' => 'required|string',
            'carte_id' => 'nullable|string',
            'duration_sivp' => 'nullable|string',
            'cin' => 'nullable|unique:users',
            'delivery_date_cin' => 'nullable|date',
            'delivery_place_cin' => 'nullable|string',
            'num_passport' => 'nullable|string|unique:users',
            'integration_date' => 'required|date',
            'motivation' => 'array',
            'regime_social' => 'required|string',
            'text' => 'nullable|string'
        ];
    }
}
