<?php

declare(strict_types=1);

namespace App\Models\Sage;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sage `_ccg_EB_BillingRuns` — a run that raised charges for a billing period.
 * `Number` is the run reference, `PeriodID` the period billed, and `Processed`
 * whether it has been finalised (posted to the ledger).
 */
class BillingRun extends SageModel
{
    protected $table = '_ccg_EB_BillingRuns';

    protected function casts(): array
    {
        return [
            'Processed' => 'boolean',
            'ProcessedDate' => 'datetime',
            'SavedDate' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'PeriodID');
    }
}
