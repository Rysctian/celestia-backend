<?php

namespace App\Models;

use Database\Factories\OvertimeApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeApplication extends Model
{
    /** @use HasFactory<OvertimeApplicationFactory> */
    use HasFactory;

    protected $fillable = ['employee_id', 'overtime_date', 'time_from', 'time_to', 'reason', 'allow_approver', 'status', 'approved_at', 'approved_by', 'rejection_reason'];

    protected function casts(): array
    {
        return ['overtime_date' => 'immutable_date:Y-m-d', 'allow_approver' => 'boolean', 'approved_at' => 'immutable_datetime'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
