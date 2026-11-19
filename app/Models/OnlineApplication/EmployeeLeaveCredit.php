<?php

namespace App\Models\OnlineApplication;

use App\Models\Employee;
use App\Queries\OnlineApplication\EmployeeLeaveCreditQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveCredit extends Model
{
    use EmployeeLeaveCreditQuery, HasFactory;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'allocated_credits',
        'used_credits',
        'pending_credits',
    ];

    protected $appends = ['available_credits'];

    protected function casts(): array
    {
        return [
            'leave_type_id' => 'integer',
            'year' => 'integer',
            'allocated_credits' => 'float',
            'used_credits' => 'float',
            'pending_credits' => 'float',
        ];
    }

    public function getAvailableCreditsAttribute(): float
    {
        return round((float) $this->allocated_credits - (float) $this->used_credits - (float) $this->pending_credits, 2);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function logs()
    {
        return $this->hasMany(EmployeeLeaveCreditLog::class)->orderByDesc('id');
    }
}
