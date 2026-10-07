<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\EmployeeLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLog extends Model
{
    /** @use HasFactory<EmployeeLogFactory> */
    use HasFactory;

    protected $fillable = ['employee_id', 'logged_at', 'device_id', 'source'];

    protected function casts(): array
    {
        return ['logged_at' => 'immutable_datetime'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function attendanceLogs()
    {
        return $this->hasMany(EmployeeAttendanceLog::class);
    }

    public function scopeForDate(Builder $query, string $employeeId, string $date)
    {
        return $query
            ->where('employee_id', $employeeId)
            ->whereDate('logged_at', $date)
            ->orderBy('logged_at');
    }

    public function scopeforDateBetween(Builder $query, string $employeeId, string $dateFrom, string $dateTo)
    {
        return $query
            ->where('employee_id', $employeeId)
            ->whereBetween('logged_at', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay(),
            ])
            ->orderBy('logged_at');
    }
}
