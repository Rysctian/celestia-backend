<?php

namespace App\Models;

use App\Queries\ScheduleQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory, ScheduleQuery;

    protected $fillable = ['name', 'timezone', 'attendance_policy_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'attendance_policy_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function attendancePolicy()
    {
        return $this->belongsTo(AttendancePolicy::class);
    }

    public function details()
    {
        return $this->hasMany(ScheduleDetail::class)->orderBy('day_of_week')->orderBy('start_time');
    }

    public function employeeSchedules()
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    public function attendance()
    {
        return $this->hasMany(EmployeeAttendance::class);
    }
}
