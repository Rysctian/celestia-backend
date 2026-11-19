<?php

namespace App\Models\OnlineApplication;

use Database\Factories\OnlineApplication\CorrectionApplicationDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorrectionApplicationDetail extends Model
{
    /** @use HasFactory<CorrectionApplicationDetailFactory> */
    use HasFactory;

    protected $fillable = [
        'correction_application_id',
        'date',
        'old_am_in',
        'old_am_out',
        'old_pm_in',
        'old_pm_out',
        'am_in',
        'am_out',
        'pm_in',
        'pm_out',
    ];

    protected function casts(): array
    {
        return ['date' => 'immutable_date:Y-m-d'];
    }

    public function application()
    {
        return $this->belongsTo(CorrectionApplication::class, 'correction_application_id');
    }
}
