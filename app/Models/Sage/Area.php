<?php

declare(strict_types=1);

namespace App\Models\Sage;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sage `_ccg_EB_Areas` — the billing-level suburb/neighbourhood a property sits
 * in (e.g. CBD, Mathendele). `Name` holds a numeric code and `Description` the
 * human name.
 */
class Area extends SageModel
{
    protected $table = '_ccg_EB_Areas';

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'AreaID');
    }
}
