<?php

declare(strict_types=1);

namespace App\Models\Sage;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sage `_ccg_EB_Tariffs` — a named rate schedule for a service, usually per
 * property category (e.g. "Water Cons RES", "Refuse H/D"). The actual money is
 * in its `bands` (a single band for flat charges, several for block tariffs).
 */
class Tariff extends SageModel
{
    protected $table = '_ccg_EB_Tariffs';

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'ServiceID');
    }

    public function bands(): HasMany
    {
        return $this->hasMany(TariffBand::class, 'TariffID');
    }

    public function billingTrCode(): BelongsTo
    {
        return $this->belongsTo(TrCode::class, 'BillingTrCodeID');
    }
}
