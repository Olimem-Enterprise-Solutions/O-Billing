<?php

declare(strict_types=1);

namespace App\Models\Sage;

/**
 * Sage `TrCodes` — a transaction (revenue) code a charge posts to in the GL.
 * `Code` is the short code shown on invoices; `Tax` flags whether the charge is
 * taxable.
 */
class TrCode extends SageModel
{
    protected $table = 'TrCodes';

    protected $primaryKey = 'idTrCodes';

    protected function casts(): array
    {
        return [
            'Tax' => 'boolean',
        ];
    }
}
