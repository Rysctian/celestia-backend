<?php

namespace App\Models\OnlineApplication;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveApplicationDetail extends Model
{
    use HasFactory;

    public const DURATION_TYPES = ['whole_day', 'half_day_am', 'half_day_pm'];

    protected $fillable = [
        'leave_application_id',
        'date',
        'duration_type',
        'day_value',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date:Y-m-d',
            'day_value' => 'float',
        ];
    }

    public function application()
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id');
    }
}
