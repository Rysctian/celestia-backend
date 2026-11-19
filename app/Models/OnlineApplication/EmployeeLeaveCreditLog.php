<?php

namespace App\Models\OnlineApplication;

use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveCreditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'employee_leave_credit_id',
        'change_type',
        'amount',
        'balance_after',
        'leave_application_id',
        'remarks',
        'acted_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'balance_after' => 'float',
            'leave_application_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function credit()
    {
        return $this->belongsTo(EmployeeLeaveCredit::class, 'employee_leave_credit_id');
    }
}
