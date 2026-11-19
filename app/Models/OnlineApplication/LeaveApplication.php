<?php

namespace App\Models\OnlineApplication;

use App\Models\Employee;
use App\Queries\OnlineApplication\LeaveApplicationQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveApplication extends Model
{
    use HasFactory, LeaveApplicationQuery;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'date_from',
        'date_to',
        'total_days',
        'reason',
        'attachment_path',
        'attachment_name',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'leave_type_id' => 'integer',
            'date_from' => 'immutable_date:Y-m-d',
            'date_to' => 'immutable_date:Y-m-d',
            'total_days' => 'float',
            'submitted_at' => 'immutable_datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function details()
    {
        return $this->hasMany(LeaveApplicationDetail::class)->orderBy('date')->orderBy('id');
    }

    public function approvals()
    {
        return $this->morphMany(ApplicationApproval::class, 'application')->orderBy('sequence');
    }
}
