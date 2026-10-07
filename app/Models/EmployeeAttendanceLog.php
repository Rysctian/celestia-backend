<?php

namespace App\Models;

use Database\Factories\EmployeeAttendanceLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendanceLog extends Model
{
    /** @use HasFactory<EmployeeAttendanceLogFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_attendance_id',
        'employee_log_id',
        'sequence',
        'log_type',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
        ];
    }

    public function attendance()
    {
        return $this->belongsTo(EmployeeAttendance::class, 'employee_attendance_id');
    }

    public function employeeLog()
    {
        return $this->belongsTo(EmployeeLog::class);
    }
}
