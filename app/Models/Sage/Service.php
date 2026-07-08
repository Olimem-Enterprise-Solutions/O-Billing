<?php

declare(strict_types=1);

namespace App\Models\Sage;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sage `_ccg_EB_Services` — a billable service within a group. `CalculationMethod`
 * (1 = fixed, 2 = metered/consumption, 3 = rated) drives how a charge is worked
 * out, and `MeasurableUnit` (e.g. KL) applies to metered services.
 */
class Service extends SageModel
{
    protected $table = '_ccg_EB_Services';

    public function group(): BelongsTo
    {
        return $this->belongsTo(ServiceGroup::class, 'ServiceGroupID');
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(Tariff::class, 'ServiceID');
    }
}
