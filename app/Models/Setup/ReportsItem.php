<?php

namespace App\Models\Setup;

use App\Models\Employee;
use App\Queries\Setup\ReportsItemQuery;
use Illuminate\Database\Eloquent\Model;

class ReportsItem extends Model
{
    use ReportsItemQuery;

    protected $fillable = [
        'code',
        'name',
        'office_id',
        'department_id',
        'division_id',
        'section_id',
        'position_id',
        'reports_to_position_id',
        'reports_to_employee_id',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'office_id' => 'integer',
            'department_id' => 'integer',
            'division_id' => 'integer',
            'section_id' => 'integer',
            'position_id' => 'integer',
            'reports_to_position_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function reportsToPosition()
    {
        return $this->belongsTo(Position::class, 'reports_to_position_id');
    }

    public function reportsToEmployee()
    {
        return $this->belongsTo(Employee::class, 'reports_to_employee_id', 'employee_id');
    }
}
