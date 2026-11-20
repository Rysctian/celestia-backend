<?php

namespace App\Models\Setup;

use App\Models\Employee;
use App\Queries\Setup\OfficeQuery;
use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    use OfficeQuery;

    public const TYPES = ['office', 'college', 'campus', 'branch'];

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'type',
        'parent_id',
        'head_employee_id',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function headEmployee()
    {
        return $this->belongsTo(Employee::class, 'head_employee_id', 'employee_id');
    }

    public function departments()
    {
        return $this->hasMany(Department::class, 'office_id')->orderBy('sort_order')->orderBy('name');
    }

    public function reportsItems()
    {
        return $this->hasMany(ReportsItem::class, 'office_id');
    }
}
