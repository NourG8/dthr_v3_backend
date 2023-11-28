<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

class EditUserInternRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'last_name' => 'required|string',
            'first_name' => 'required|string',
            'phone' => 'required|string',
            'address' => 'required|string',
            'Family_situation' => 'required|string',
            'nb_children' => 'required|integer',
            'level_studies' => 'required|string',
            'delivery_date_cin' => 'required|date',
           ];
    }
}
