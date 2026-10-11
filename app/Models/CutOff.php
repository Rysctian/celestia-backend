<?php

namespace App\Models;

use App\Queries\CutOffQuery;
use Database\Factories\CutOffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CutOff extends Model
{
    /** @use HasFactory<CutOffFactory> */
    use CutOffQuery, HasFactory;

    protected $fillable = ['schedule_type', 'name', 'year', 'month', 'is_active'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer', 'is_active' => 'boolean'];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(CutOffPeriod::class)->orderBy('cut_off_no');
    }
}
