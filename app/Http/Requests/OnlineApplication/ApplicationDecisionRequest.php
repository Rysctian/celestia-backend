<?php

namespace App\Http\Requests\OnlineApplication;

use Illuminate\Foundation\Http\FormRequest;

class ApplicationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The approval service checks the current approver/owner.
    }

    public function rules(): array
    {
        return ['remarks' => ['sometimes', 'nullable', 'string', 'max:5000']];
    }
}
