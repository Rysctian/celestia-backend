<?php

namespace App\Models\OnlineApplication;

use App\Models\Employee;
use App\Queries\OnlineApplication\OfficialBusinessApplicationQuery;
use Database\Factories\OnlineApplication\OfficialBusinessApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfficialBusinessApplication extends Model
{
    /** @use HasFactory<OfficialBusinessApplicationFactory> */
    use HasFactory, OfficialBusinessApplicationQuery;

    public const OB_TYPES = ['ob', 'wfh'];

    protected $fillable = ['employee_id', 'ob_type', 'destination', 'reason', 'status', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function details()
    {
        return $this->hasMany(OfficialBusinessApplicationDetail::class)->orderBy('date')->orderBy('start_time')->orderBy('id');
    }

    public function approvals()
    {
        return $this->morphMany(ApplicationApproval::class, 'application')->orderBy('sequence');
    }
}
