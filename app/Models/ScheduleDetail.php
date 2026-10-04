<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id', 'day_of_week', 'is_rest_day', 'start_time', 'end_time',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_rest_day' => 'boolean',
            'unpaid_break_minutes' => 'integer',
        ];
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
}
