<?php

namespace App\Models;

use Database\Factories\CutOffPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CutOffPeriod extends Model
{
    /** @use HasFactory<CutOffPeriodFactory> */
    use HasFactory;

    protected $fillable = [
        'cut_off_id', 'cut_off_no', 'dtr_date_from', 'dtr_date_to', 'no_dtr',
        'confirmation_start_date', 'confirmation_end_date',
        'confirmation_time_from', 'confirmation_time_to',
        'payroll_date_from', 'payroll_date_to', 'release_date',
    ];

    protected function casts(): array
    {
        return [
            'cut_off_no' => 'integer', 'no_dtr' => 'boolean',
            'dtr_date_from' => 'immutable_date:Y-m-d', 'dtr_date_to' => 'immutable_date:Y-m-d',
            'confirmation_start_date' => 'immutable_date:Y-m-d',
            'confirmation_end_date' => 'immutable_date:Y-m-d',
            'payroll_date_from' => 'immutable_date:Y-m-d', 'payroll_date_to' => 'immutable_date:Y-m-d',
            'release_date' => 'immutable_date:Y-m-d',
        ];
    }

    public function cutOff(): BelongsTo
    {
        return $this->belongsTo(CutOff::class);
    }
}
