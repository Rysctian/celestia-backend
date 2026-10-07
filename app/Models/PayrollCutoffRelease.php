<?php

namespace App\Models;

use Database\Factories\PayrollCutoffReleaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollCutoffRelease extends Model
{
    /** @use HasFactory<PayrollCutoffReleaseFactory> */
    use HasFactory;

    protected $fillable = ['payroll_cutoff_id', 'cutoff_no', 'release_date'];

    protected function casts(): array
    {
        return [
            'cutoff_no' => 'integer',
            'release_date' => 'immutable_date:Y-m-d',
        ];
    }

    public function payrollCutoff()
    {
        return $this->belongsTo(PayrollCutoff::class);
    }
}
