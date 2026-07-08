<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sage;

use Filament\Resources\Resource;
use UnitEnum;

/**
 * Shared base for the "Sage (Live)" read-only resources. These browse the live
 * Sage Evolution database directly (on the `sage` connection) and never write to
 * it, so creation is disabled and — because the Sage tables have no municipality
 * column — tenant scoping is switched off.
 */
abstract class SageResource extends Resource
{
    protected static string|UnitEnum|null $navigationGroup = 'Sage (Live)';

    protected static bool $isScopedToTenant = false;

    public static function canCreate(): bool
    {
        return false;
    }
}
