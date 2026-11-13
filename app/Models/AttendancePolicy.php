<?php

namespace App\Models;

use App\Queries\AttendancePolicyQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendancePolicy extends Model
{
    use AttendancePolicyQuery, HasFactory;

    public const COUNT_FROM_SCHEDULE_START = 'schedule_start';

    public const COUNT_FROM_AFTER_GRACE = 'after_grace';

    protected $fillable = [
        'name',
        'description',
        'tardy_grace_seconds',
        'tardy_count_from',
        'absent_after_late_seconds',
        'undertime_grace_seconds',
        'early_in_window_seconds',
        'late_out_window_seconds',
        'duplicate_tap_window_seconds',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tardy_grace_seconds' => 'integer',
            'absent_after_late_seconds' => 'integer',
            'undertime_grace_seconds' => 'integer',
            'early_in_window_seconds' => 'integer',
            'late_out_window_seconds' => 'integer',
            'duplicate_tap_window_seconds' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}
