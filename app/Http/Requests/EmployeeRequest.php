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

    public function rules(): array
    {
        return match ($this->method()) {
            'GET' => $this->searchRules(),
            'POST' => $this->storeRules(),
            default => [],
        };
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function searchRules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'gender' => ['nullable', 'string'],
            'civil_status' => ['nullable', 'string'],
            'employee_id' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_by' => ['nullable', 'in:id,employee_id,first_name,last_name,created_at'],
            'sort_order' => ['nullable', 'in:asc,desc'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function storeRules(): array
    {
        return [
            'type' => ['required', 'nullable', 'string'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['required', 'nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'name_extension' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['required', 'nullable', 'date'],
            'birth_place' => ['required', 'nullable', 'string', 'max:255'],
            'gender' => ['required', 'nullable', 'string', 'max:20'],
            'civil_status' => ['required', 'nullable', 'string', 'max:30'],
            'personal_email' => ['required', 'nullable', 'email'],
            'mobile_no' => ['required', 'nullable', 'string', 'max:30'],
            'telephone_no' => ['required', 'nullable', 'string', 'max:30'],
        ];
    }
}
