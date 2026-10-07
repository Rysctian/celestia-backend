<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceException extends Model
{
    use HasFactory;

    public const TYPES = ['leave', 'official_business', 'holiday', 'suspension', 'schedule_cancellation'];

    protected $fillable = ['employee_id', 'date', 'type', 'reason', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date:Y-m-d', 'approved_at' => 'immutable_datetime'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
