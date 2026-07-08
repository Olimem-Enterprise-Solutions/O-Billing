<?php

declare(strict_types=1);

namespace App\Models\Sage;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sage `_ccg_EB_ServiceGroups` — the top grouping of billable services
 * (Water Consumption, Sewerage Charges, Refuse Charges, Assessment Rate, …).
 */
class ServiceGroup extends SageModel
{
    protected $table = '_ccg_EB_ServiceGroups';

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'ServiceGroupID');
    }
}
