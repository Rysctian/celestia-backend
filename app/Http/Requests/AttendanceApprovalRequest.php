<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasMenuAccess('attendance', 'update');
    }

    public function rules(): array
    {
        return [];
    }
}
