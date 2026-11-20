<?php

namespace App\Models\Setup;

use App\Models\Employee;
use App\Queries\Setup\ClearanceSignatoryQuery;
use Illuminate\Database\Eloquent\Model;

class ClearanceSignatory extends Model
{
    use ClearanceSignatoryQuery;

    protected $fillable = [
        'clearance_type_id',
        'clearance_requirement_id',
        'office_id',
        'department_id',
        'position_id',
        'employee_id',
        'signatory_label',
        'sign_order',
        'is_required',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'clearance_type_id' => 'integer',
            'clearance_requirement_id' => 'integer',
            'office_id' => 'integer',
            'department_id' => 'integer',
            'position_id' => 'integer',
            'sign_order' => 'integer',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function clearanceType()
    {
        return $this->belongsTo(ClearanceType::class, 'clearance_type_id');
    }

    public function clearanceRequirement()
    {
        return $this->belongsTo(ClearanceRequirement::class, 'clearance_requirement_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
