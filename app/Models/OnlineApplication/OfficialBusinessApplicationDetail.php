<?php

namespace App\Models\OnlineApplication;

use Database\Factories\OnlineApplication\OfficialBusinessApplicationDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfficialBusinessApplicationDetail extends Model
{
    /** @use HasFactory<OfficialBusinessApplicationDetailFactory> */
    use HasFactory;

    protected $fillable = ['official_business_application_id', 'date', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date:Y-m-d'];
    }

    public function application()
    {
        return $this->belongsTo(OfficialBusinessApplication::class, 'official_business_application_id');
    }
}
