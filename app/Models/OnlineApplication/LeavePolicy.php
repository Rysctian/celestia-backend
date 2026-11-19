<?php

namespace App\Models\OnlineApplication;

use App\Queries\OnlineApplication\LeavePolicyQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeavePolicy extends Model
{
    use HasFactory, LeavePolicyQuery;

    public const ACCRUAL_MODES = ['upfront_annual', 'monthly_accrual', 'none'];

    protected $fillable = [
        'name',
        'description',
        'min_notice_days',
        'allow_backdated_filing',
        'max_backdated_days',
        'max_consecutive_days',
        'allow_half_day',
        'accrual_mode',
        'allow_negative_balance',
        'carry_over_enabled',
        'carry_over_max_days',
        'count_rest_days',
        'allow_attachments',
        'require_attachment',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_notice_days' => 'integer',
            'allow_backdated_filing' => 'boolean',
            'max_backdated_days' => 'integer',
            'max_consecutive_days' => 'integer',
            'allow_half_day' => 'boolean',
            'allow_negative_balance' => 'boolean',
            'carry_over_enabled' => 'boolean',
            'carry_over_max_days' => 'float',
            'count_rest_days' => 'boolean',
            'allow_attachments' => 'boolean',
            'require_attachment' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function leaveTypes()
    {
        return $this->hasMany(LeaveType::class);
    }
}
