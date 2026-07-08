<?php

declare(strict_types=1);

namespace App\Models\Sage;

/**
 * Sage `_etblPeriod` — a financial period. `idPeriod` is referenced by billing
 * runs and tariff bands; `dPeriodDate` is the period's (month-end) date.
 */
class Period extends SageModel
{
    protected $table = '_etblPeriod';

    protected $primaryKey = 'idPeriod';

    protected function casts(): array
    {
        return [
            'dPeriodDate' => 'datetime',
        ];
    }
}
