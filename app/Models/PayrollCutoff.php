<?php

namespace App\Models;

use App\Queries\PayrollCutoffQuery;
use Database\Factories\PayrollCutoffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollCutoff extends Model
{
    /** @use HasFactory<PayrollCutoffFactory> */
    use HasFactory, PayrollCutoffQuery;

    protected $fillable = [
        'schedule_type',
        'quarter',
        'start_date',
        'end_date',
        'no_dtr',
        'dtr_cutoff_from',
        'dtr_cutoff_to',
        'dtr_confirmation_start',
        'dtr_confirmation_end',
        'dtr_confirmation_time_from',
        'dtr_confirmation_time_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quarter' => 'integer',
            'start_date' => 'immutable_date:Y-m-d',
            'end_date' => 'immutable_date:Y-m-d',
            'no_dtr' => 'boolean',
            'dtr_cutoff_from' => 'immutable_date:Y-m-d',
            'dtr_cutoff_to' => 'immutable_date:Y-m-d',
            'dtr_confirmation_start' => 'immutable_date:Y-m-d',
            'dtr_confirmation_end' => 'immutable_date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function releases()
    {
        return $this->hasMany(PayrollCutoffRelease::class)->orderBy('cutoff_no');
    }
}
