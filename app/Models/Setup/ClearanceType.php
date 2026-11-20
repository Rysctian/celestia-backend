<?php

namespace App\Models\Setup;

use App\Queries\Setup\ClearanceTypeQuery;
use Illuminate\Database\Eloquent\Model;

class ClearanceType extends Model
{
    use ClearanceTypeQuery;

    public const APPLIES_TO_OPTIONS = [
        'separation',
        'transfer',
        'leave',
        'onboarding',
        'annual',
        'other',
    ];

    protected $fillable = [
        'code',
        'name',
        'description',
        'applies_to',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function requirements()
    {
        return $this->hasMany(ClearanceRequirement::class, 'clearance_type_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function signatories()
    {
        return $this->hasMany(ClearanceSignatory::class, 'clearance_type_id')
            ->orderBy('sign_order')
            ->orderBy('id');
    }
}
