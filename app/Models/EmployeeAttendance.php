<?php

namespace App\Models;

use App\Queries\AttendanceQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendance extends Model
{
    use AttendanceQuery, HasFactory;

    protected $table = 'employee_attendance';

    protected $fillable = ['employee_id', 'date', 'schedule_id', 'schedule_detail_id', 'overtime_application_id', 'sched_start', 'sched_end', 'time_in', 'time_out', 'tardy', 'tardy_seconds', 'undertime', 'undertime_seconds', 'absent', 'early_dismiss', 'early_dismiss_seconds', 'missing_time_in', 'missing_time_out', 'status', 'remarks', 'details', 'computed_at', 'confirmed_at'];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date:Y-m-d', 'sched_start' => 'immutable_datetime', 'sched_end' => 'immutable_datetime',
            'time_in' => 'immutable_datetime', 'time_out' => 'immutable_datetime', 'computed_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime',
            'tardy' => 'boolean', 'undertime' => 'boolean', 'absent' => 'boolean', 'early_dismiss' => 'boolean',
            'missing_time_in' => 'boolean', 'missing_time_out' => 'boolean', 'details' => 'array',
            'tardy_seconds' => 'integer', 'undertime_seconds' => 'integer', 'early_dismiss_seconds' => 'integer',
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

    public function scheduleDetail()
    {
        return $this->belongsTo(ScheduleDetail::class);
    }

    public function corrections()
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    public function overtimeApplication()
    {
        return $this->belongsTo(OvertimeApplication::class);
    }
}
