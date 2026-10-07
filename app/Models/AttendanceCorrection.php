<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrection extends Model
{
    use HasFactory;

    protected $fillable = ['employee_attendance_id', 'time_in', 'time_out', 'reason', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['time_in' => 'immutable_datetime', 'time_out' => 'immutable_datetime', 'approved_at' => 'immutable_datetime'];
    }

    public function attendance()
    {
        return $this->belongsTo(EmployeeAttendance::class, 'employee_attendance_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
