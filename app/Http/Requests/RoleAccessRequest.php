<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoleAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'access' => ['present', 'array'],
            'access.*.menu_id' => ['required', 'integer', 'distinct', 'exists:menus,id'],
            'access.*.can_view' => ['required', 'boolean'],
            'access.*.can_create' => ['required', 'boolean'],
            'access.*.can_update' => ['required', 'boolean'],
            'access.*.can_delete' => ['required', 'boolean'],
        ];
    }

    protected function passedValidation(): void
    {
        foreach ($this->validated('access') as $index => $grant) {
            if (! $grant['can_view'] && ($grant['can_create'] || $grant['can_update'] || $grant['can_delete'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "access.$index.can_view" => 'View access is required for other actions.',
                ]);
            }
        }
    }
}
