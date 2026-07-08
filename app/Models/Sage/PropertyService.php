<?php

declare(strict_types=1);

namespace App\Models\Sage;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sage `_ccg_EB_PropertyServices` — the link that says a property is billed for
 * a given service on a given tariff (and, for metered services, meter), to a
 * given customer. `Billable` flags whether it currently raises charges.
 */
class PropertyService extends SageModel
{
    protected $table = '_ccg_EB_PropertyServices';

    protected function casts(): array
    {
        return [
            'Billable' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'PropertyID');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'ServiceID');
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class, 'TariffID');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'CustomerID', 'DCLink');
    }
}
