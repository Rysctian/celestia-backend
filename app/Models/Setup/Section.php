<?php

namespace App\Models\Setup;

use App\Models\Employee;
use App\Queries\Setup\SectionQuery;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use SectionQuery;

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'division_id',
        'department_id',
        'head_employee_id',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'division_id' => 'integer',
            'department_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function headEmployee()
    {
        return $this->belongsTo(Employee::class, 'head_employee_id', 'employee_id');
    }

    public function positions()
    {
        return $this->hasMany(Position::class, 'section_id')->orderBy('sort_order')->orderBy('title');
    }

    public function reportsItems()
    {
        return $this->hasMany(ReportsItem::class, 'section_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'section_id');
    }
}
