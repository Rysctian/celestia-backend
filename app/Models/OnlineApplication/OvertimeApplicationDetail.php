<?php

namespace App\Models\OnlineApplication;

use Database\Factories\OnlineApplication\OvertimeApplicationDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeApplicationDetail extends Model
{
    /** @use HasFactory<OvertimeApplicationDetailFactory> */
    use HasFactory;

    protected $fillable = ['overtime_application_id', 'date', 'start_time', 'end_time', 'break_seconds', 'requested_seconds'];

    protected $appends = ['break_duration'];

    public function getBreakDurationAttribute(): string
    {
        return sprintf('%02d:%02d', intdiv($this->break_seconds, 3600), intdiv($this->break_seconds % 3600, 60));
    }

    protected function casts(): array
    {
        return ['date' => 'immutable_date:Y-m-d', 'break_seconds' => 'integer', 'requested_seconds' => 'integer'];
    }

    public function application()
    {
        return $this->belongsTo(OvertimeApplication::class, 'overtime_application_id');
    }
}
