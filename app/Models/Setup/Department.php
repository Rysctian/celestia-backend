<?php

namespace App\Models\Setup;

use App\Models\Employee;
use App\Queries\Setup\DepartmentQuery;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use DepartmentQuery;

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'office_id',
        'head_employee_id',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'office_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function headEmployee()
    {
        return $this->belongsTo(Employee::class, 'head_employee_id', 'employee_id');
    }

    public function divisions()
    {
        return $this->hasMany(Division::class, 'department_id')->orderBy('sort_order')->orderBy('name');
    }

    public function sections()
    {
        return $this->hasMany(Section::class, 'department_id')->orderBy('sort_order')->orderBy('name');
    }

    public function positions()
    {
        return $this->hasMany(Position::class, 'department_id')->orderBy('sort_order')->orderBy('title');
    }

    public function reportsItems()
    {
        return $this->hasMany(ReportsItem::class, 'department_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'department_id');
    }

    public function clearanceRequirements()
    {
        return $this->hasMany(ClearanceRequirement::class, 'department_id');
    }

    public function clearanceSignatories()
    {
        return $this->hasMany(ClearanceSignatory::class, 'department_id');
    }
}
