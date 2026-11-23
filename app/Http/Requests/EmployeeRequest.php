<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->method()) {
            'GET' => $this->searchRules(),
            'POST' => $this->storeRules(),
            'PUT', 'PATCH' => $this->updateRules(),
            default => [],
        };
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function searchRules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'type' => ['nullable', is_array($this->input('type')) ? 'array' : 'string'],
            'type.*' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', is_array($this->input('gender')) ? 'array' : 'string'],
            'gender.*' => ['required', 'string', 'max:255'],
            'civil_status' => ['nullable', is_array($this->input('civil_status')) ? 'array' : 'string'],
            'civil_status.*' => ['required', 'string', 'max:255'],
            'employment_status' => ['nullable', is_array($this->input('employment_status')) ? 'array' : 'string'],
            'employment_status.*' => ['required', 'string', 'max:255'],
            'work_arrangement' => ['nullable', is_array($this->input('work_arrangement')) ? 'array' : 'string'],
            'work_arrangement.*' => ['required', 'string', 'max:255'],
            'employee_category' => ['nullable', is_array($this->input('employee_category')) ? 'array' : 'string'],
            'employee_category.*' => ['required', 'string', 'max:255'],
            'office_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'position_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_by' => ['nullable', 'in:id,employee_id,first_name,last_name,date_hired,created_at'],
            'sort_order' => ['nullable', 'in:asc,desc'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function storeRules(): array
    {
        return [
            'type' => ['required', 'nullable', 'string', 'max:60'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['required', 'nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'name_extension' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['required', 'nullable', 'date'],
            'birth_place' => ['required', 'nullable', 'string', 'max:255'],
            'gender' => ['required', 'nullable', 'string', 'max:40'],
            'civil_status' => ['required', 'nullable', 'string', 'max:40'],
            'personal_email' => ['required', 'nullable', 'email', Rule::unique('users', 'email')],
            'mobile_no' => ['required', 'nullable', 'string', 'max:30'],
            'telephone_no' => ['required', 'nullable', 'string', 'max:30'],
            ...$this->extendedProfileRules(),
        ];
    }

    public function updateRules(): array
    {
        $employeeId = (string) $this->route('employee_id');
        $userId = Employee::where('employee_id', $employeeId)
            ->first()
            ?->user()
            ->value('id');

        return [
            'type' => ['sometimes', 'nullable', 'string', 'max:60'],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'name_extension' => ['sometimes', 'nullable', 'string', 'max:20'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'birth_place' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:40'],
            'civil_status' => ['sometimes', 'nullable', 'string', 'max:40'],
            'personal_email' => [
                'sometimes',
                'nullable',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'mobile_no' => ['sometimes', 'nullable', 'string', 'max:30'],
            'telephone_no' => ['sometimes', 'nullable', 'string', 'max:30'],
            ...$this->extendedProfileRules(),
        ];
    }

    private function extendedProfileRules(): array
    {
        return [
            // 01. Organizational Placement
            'office_id' => ['sometimes', 'nullable', 'integer', 'exists:offices,id'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'division_id' => ['sometimes', 'nullable', 'integer', 'exists:divisions,id'],
            'section_id' => ['sometimes', 'nullable', 'integer', 'exists:sections,id'],
            'position_id' => ['sometimes', 'nullable', 'integer', 'exists:positions,id'],
            'reports_to_employee_id' => ['sometimes', 'nullable', 'string', 'max:30', 'exists:employees,employee_id'],

            // 02. Employee Information Setup
            'blood_type' => ['sometimes', 'nullable', 'string', 'max:30'],
            'country' => ['sometimes', 'nullable', 'string', 'max:60'],
            'nationality' => ['sometimes', 'nullable', 'string', 'max:60'],
            'citizenship' => ['sometimes', 'nullable', 'string', 'max:60'],
            'religion' => ['sometimes', 'nullable', 'string', 'max:80'],
            'educational_attainment' => ['sometimes', 'nullable', 'string', 'max:80'],
            'degree_course' => ['sometimes', 'nullable', 'string', 'max:120'],
            'languages' => ['sometimes', 'nullable', 'array'],
            'languages.*' => ['required', 'string', 'max:60'],
            'disability_type' => ['sometimes', 'nullable', 'string', 'max:80'],
            'current_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'emergency_contact_relationship' => ['sometimes', 'nullable', 'string', 'max:60'],
            'emergency_contact_no' => ['sometimes', 'nullable', 'string', 'max:30'],

            // 03. Employment Setup
            'employment_status' => ['sometimes', 'nullable', 'string', 'max:60'],
            'employee_category' => ['sometimes', 'nullable', 'string', 'max:60'],
            'appointment_type' => ['sometimes', 'nullable', 'string', 'max:60'],
            'employment_classification' => ['sometimes', 'nullable', 'string', 'max:60'],
            'work_arrangement' => ['sometimes', 'nullable', 'string', 'max:60'],
            'work_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'date_hired' => ['sometimes', 'nullable', 'date'],
            'regularization_date' => ['sometimes', 'nullable', 'date'],
            'separation_type' => ['sometimes', 'nullable', 'string', 'max:60'],
            'separation_reason' => ['sometimes', 'nullable', 'string', 'max:120'],
            'separation_date' => ['sometimes', 'nullable', 'date'],

            // Statutory IDs
            'tin_no' => ['sometimes', 'nullable', 'string', 'max:30'],
            'sss_no' => ['sometimes', 'nullable', 'string', 'max:30'],
            'philhealth_no' => ['sometimes', 'nullable', 'string', 'max:30'],
            'pagibig_no' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }
}
