<?php

namespace App\Models\OnlineApplication;

use App\Models\Employee;
use Database\Factories\OnlineApplication\ApplicationApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationApproval extends Model
{
    /** @use HasFactory<ApplicationApprovalFactory> */
    use HasFactory;

    protected $fillable = ['employee_id', 'application_type', 'application_id', 'approver_id', 'sequence', 'status', 'remarks', 'acted_at'];

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'acted_at' => 'immutable_datetime'];
    }

    public function application()
    {
        return $this->morphTo();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approver_id', 'employee_id');
    }
}
