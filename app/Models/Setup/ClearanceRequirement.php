<?php

namespace App\Models\Setup;

use App\Queries\Setup\ClearanceRequirementQuery;
use Illuminate\Database\Eloquent\Model;

class ClearanceRequirement extends Model
{
    use ClearanceRequirementQuery;

    protected $fillable = [
        'clearance_type_id',
        'code',
        'name',
        'description',
        'office_id',
        'department_id',
        'is_mandatory',
        'requires_attachment',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'clearance_type_id' => 'integer',
            'office_id' => 'integer',
            'department_id' => 'integer',
            'is_mandatory' => 'boolean',
            'requires_attachment' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function clearanceType()
    {
        return $this->belongsTo(ClearanceType::class, 'clearance_type_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function signatories()
    {
        return $this->hasMany(ClearanceSignatory::class, 'clearance_requirement_id')
            ->orderBy('sign_order')
            ->orderBy('id');
    }
}
