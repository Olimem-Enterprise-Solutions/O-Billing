<?php

declare(strict_types=1);

namespace App\Models\Sage;

use Illuminate\Database\Eloquent\Model;

/**
 * Base class for read-only mirrors of the Sage Evolution (CCG Enterprise
 * Billing) tables on the `sage` SQL Server connection. These models exist only
 * to READ the live source database — for the "Sage (Live)" browser and the
 * `sage:import` command — so all writes are hard-disabled. Sage tables use an
 * `ID` integer primary key and their own DateCreated/DateLastUpdated columns
 * rather than Laravel timestamps.
 */
abstract class SageModel extends Model
{
    protected $connection = 'sage';

    protected $primaryKey = 'ID';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $guarded = [];

    /** Never write to the source system. */
    public function save(array $options = []): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }
}
