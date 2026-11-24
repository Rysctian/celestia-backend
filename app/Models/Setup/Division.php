<?php

namespace App\Models\Setup;

use App\Models\Employee;
use App\Queries\Setup\DivisionQuery;
use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    use DivisionQuery;

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'department_id',
        'head_employee_id',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function headEmployee()
    {
        return $this->belongsTo(Employee::class, 'head_employee_id', 'employee_id');
    }

    public function sections()
    {
        return $this->hasMany(Section::class, 'division_id')->orderBy('sort_order')->orderBy('name');
    }

    public function positions()
    {
        return $this->hasMany(Position::class, 'division_id')->orderBy('sort_order')->orderBy('title');
    }

    public function reportsItems()
    {
        return $this->hasMany(ReportsItem::class, 'division_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'division_id');
    }
}
