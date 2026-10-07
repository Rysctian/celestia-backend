<?php

namespace App\Models;

use Database\Factories\EmployeeAttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendance extends Model
{
    /** @use HasFactory<EmployeeAttendanceFactory> */
    use HasFactory;

    protected $table = 'employee_attendance';

    protected $fillable = [
        'employee_id',
        'date',
        'schedule_id',
        'sched_start',
        'sched_end',
        'time_in',
        'time_out',
        'scheduled_seconds',
        'rendered_seconds',
        'absent',
        'tardy_seconds',
        'undertime',
        'undertime_seconds',
        'early_dismiss',
        'holiday',
        'suspended',
        'remarks',
        'status',
        'details',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date:Y-m-d',
            'absent' => 'boolean',
            'undertime' => 'boolean',
            'early_dismiss' => 'boolean',
            'holiday' => 'boolean',
            'suspended' => 'boolean',
            'details' => 'array',
            'confirmed_at' => 'immutable_datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function attendanceLogs()
    {
        return $this->hasMany(EmployeeAttendanceLog::class);
    }

    public function employeeLogs()
    {
        return $this->belongsToMany(EmployeeLog::class, 'employee_attendance_logs')
            ->withPivot(['id', 'sequence', 'log_type'])
            ->withTimestamps()
            ->orderByPivot('sequence');
    }

    public function getAttendanceBetweenDates(int|string $employeeid, $date_from, $date_to) {}
}
