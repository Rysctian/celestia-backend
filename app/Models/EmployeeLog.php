<?php

namespace App\Models;

use App\Queries\AttendanceQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLog extends Model
{
    use AttendanceQuery, HasFactory;

    protected $fillable = ['employee_id', 'logged_at', 'source', 'device_id', 'external_log_id', 'raw_payload'];

    protected function casts(): array
    {
        return ['logged_at' => 'immutable_datetime', 'raw_payload' => 'array'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function timesheet()
    {
        return $this->hasOne(Timesheet::class);
    }
}
