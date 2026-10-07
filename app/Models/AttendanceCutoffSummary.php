<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCutoffSummary extends Model
{
    use HasFactory;

    protected $table = 'attendance_cutoff_summary';

    protected $fillable = ['employee_id', 'date_from', 'date_to', 'scheduled_seconds', 'rendered_seconds', 'credited_seconds', 'late_seconds', 'undertime_seconds', 'break_seconds', 'overtime_rendered_seconds', 'overtime_approved_seconds', 'night_diff_seconds', 'regular_seconds', 'present_days', 'absent_days', 'incomplete_days', 'details', 'computed_at', 'confirmed_at', 'confirmed_by', 'locked_at'];

    protected function casts(): array
    {
        $casts = ['date_from' => 'immutable_date:Y-m-d', 'date_to' => 'immutable_date:Y-m-d', 'details' => 'array', 'computed_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime', 'locked_at' => 'immutable_datetime'];
        foreach ($this->fillable as $field) {
            if (str_ends_with($field, '_seconds') || str_ends_with($field, '_days')) {
                $casts[$field] = 'integer';
            }
        }

        return $casts;
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
