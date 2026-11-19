<?php

namespace App\Models\OnlineApplication;

use App\Queries\OnlineApplication\LeaveTypeQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory, LeaveTypeQuery;

    protected $fillable = [
        'code',
        'name',
        'description',
        'leave_policy_id',
        'is_paid',
        'requires_credits',
        'default_credits',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'leave_policy_id' => 'integer',
            'is_paid' => 'boolean',
            'requires_credits' => 'boolean',
            'default_credits' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function leavePolicy()
    {
        return $this->belongsTo(LeavePolicy::class);
    }

    public function credits()
    {
        return $this->hasMany(EmployeeLeaveCredit::class);
    }

    public function applications()
    {
        return $this->hasMany(LeaveApplication::class);
    }
}
