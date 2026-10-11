<?php

namespace App\Models\OnlineApplication;

use App\Models\Employee;
use Database\Factories\OnlineApplication\ApproverSetupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApproverSetup extends Model
{
    /** @use HasFactory<ApproverSetupFactory> */
    use HasFactory;

    public const APPLICATION_TYPES = ['overtime', 'official_business', 'correction', 'leave'];

    protected $table = 'approver_setup';

    protected $fillable = ['employee_id', 'application_type', 'approver_id', 'sequence'];

    protected function casts(): array
    {
        return ['sequence' => 'integer'];
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
