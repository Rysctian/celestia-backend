<?php

namespace App\Models;

use App\Queries\EmployeeScheduleQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSchedule extends Model
{
    use EmployeeScheduleQuery, HasFactory;

    protected $fillable = ['employee_id', 'schedule_id', 'effective_from', 'effective_to', 'assigned_by'];

    protected function casts(): array
    {
        return ['effective_from' => 'immutable_date:Y-m-d', 'effective_to' => 'immutable_date:Y-m-d'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeForDate($query, string $employeeId, string $date)
    {
        return $query
            ->where('employee_id', $employeeId)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from');
    }
}
