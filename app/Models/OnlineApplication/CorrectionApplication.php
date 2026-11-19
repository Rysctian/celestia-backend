<?php

namespace App\Models\OnlineApplication;

use App\Models\Employee;
use App\Queries\OnlineApplication\CorrectionApplicationQuery;
use Database\Factories\OnlineApplication\CorrectionApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorrectionApplication extends Model
{
    /** @use HasFactory<CorrectionApplicationFactory> */
    use CorrectionApplicationQuery, HasFactory;

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
        return $this->hasMany(CorrectionApplicationDetail::class)->orderBy('date')->orderBy('id');
    }

    public function approvals()
    {
        return $this->morphMany(ApplicationApproval::class, 'application')->orderBy('sequence');
    }
}
