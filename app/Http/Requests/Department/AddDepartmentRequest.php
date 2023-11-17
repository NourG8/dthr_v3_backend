<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;

class AddDepartmentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'department_name' => 'required|string',
            'department_chief' => 'required',
            'description' => 'string',
        ];
    }
}
