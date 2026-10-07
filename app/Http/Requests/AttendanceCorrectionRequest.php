<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasMenuAccess('attendance', 'update');
    }

    public function rules(): array
    {
        return [
            'time_in' => ['present', 'nullable', 'date', 'regex:/T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/'],
            'time_out' => ['present', 'nullable', 'date', 'regex:/T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
