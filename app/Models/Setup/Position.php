<?php

namespace App\Models\Setup;

use App\Models\Employee;
use App\Queries\Setup\PositionQuery;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use PositionQuery;

    protected $fillable = [
        'code',
        'title',
        'department_id',
        'division_id',
        'section_id',
        'reports_to_position_id',
        'salary_grade',
        'job_level',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'division_id' => 'integer',
            'section_id' => 'integer',
            'reports_to_position_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
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

    public function reportsToPosition()
    {
        return $this->belongsTo(self::class, 'reports_to_position_id');
    }

    public function subordinatePositions()
    {
        return $this->hasMany(self::class, 'reports_to_position_id')->orderBy('sort_order')->orderBy('title');
    }

    public function reportsItems()
    {
        return $this->hasMany(ReportsItem::class, 'position_id');
    }

    public function reportingToItems()
    {
        return $this->hasMany(ReportsItem::class, 'reports_to_position_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'position_id');
    }

    public function clearanceSignatories()
    {
        return $this->hasMany(ClearanceSignatory::class, 'position_id');
    }
}
