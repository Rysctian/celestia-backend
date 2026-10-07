<?php

namespace App\Models;

use App\Queries\AttendanceQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Timesheet extends Model
{
    use AttendanceQuery, HasFactory;

    protected $fillable = ['employee_log_id', 'employee_id', 'attendance_date', 'schedule_id', 'schedule_detail_id', 'overtime_application_id', 'logged_at', 'log_type', 'status', 'reason', 'processed_at'];

    protected function casts(): array
    {
        return ['attendance_date' => 'immutable_date:Y-m-d', 'logged_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime'];
    }

    public function employeeLog()
    {
        return $this->belongsTo(EmployeeLog::class);
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

    public function overtimeApplication()
    {
        return $this->belongsTo(OvertimeApplication::class);
    }
}
