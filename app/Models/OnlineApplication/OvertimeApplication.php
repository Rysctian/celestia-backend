<?php

namespace App\Models\OnlineApplication;

use App\Models\Employee;
use App\Queries\OnlineApplication\OvertimeApplicationQuery;
use Database\Factories\OnlineApplication\OvertimeApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeApplication extends Model
{
    /** @use HasFactory<OvertimeApplicationFactory> */
    use HasFactory, OvertimeApplicationQuery;

    protected $fillable = ['employee_id', 'reason', 'status', 'submitted_at'];

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
        return $this->hasMany(OvertimeApplicationDetail::class)->orderBy('date')->orderBy('start_time')->orderBy('id');
    }

    public function approvals()
    {
        return $this->morphMany(ApplicationApproval::class, 'application')->orderBy('sequence');
    }
}
