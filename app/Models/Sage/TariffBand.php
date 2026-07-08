<?php

declare(strict_types=1);

namespace App\Models\Sage;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sage `_ccg_EB_TariffBands` — one consumption/period band of a tariff, holding
 * the `Rate`, the block ceiling `BandEnd`, the effective period range and the
 * currency. Flat charges have a single band (BandEnd 0); block water tariffs
 * have several rising bands.
 */
class TariffBand extends SageModel
{
    protected $table = '_ccg_EB_TariffBands';

    protected function casts(): array
    {
        return [
            'Rate' => 'float',
            'BandEnd' => 'float',
        ];
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class, 'TariffID');
    }

    public function fromPeriod(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'FromPeriodID');
    }
}
