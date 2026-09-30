<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
       return [
          'type'          => ['required', 'nullable', 'string'],
          'first_name'    => ['required', 'string', 'max:100'],
          'middle_name'   => ['required', 'nullable', 'string', 'max:100'],
          'last_name'     => ['required', 'string', 'max:100'],
          'name_extension'=> ['nullable', 'string', 'max:20'],
          'birth_date'    => ['required', 'nullable', 'date'],
          'birth_place'   => ['required', 'nullable', 'string', 'max:255'],
          'gender'        => ['required', 'nullable', 'string', 'max:20'],
          'civil_status'  => ['required', 'nullable', 'string', 'max:30'],
          'personal_email'=> ['required', 'nullable', 'email'],
          'mobile_no'     => ['required', 'nullable', 'string', 'max:30'],
          'telephone_no'  => ['required', 'nullable', 'string', 'max:30'],
      ];
    }
}