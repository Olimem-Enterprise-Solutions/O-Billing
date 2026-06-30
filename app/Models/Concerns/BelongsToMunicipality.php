<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Municipality;
use App\Models\Scopes\MunicipalityScope;
use App\Support\Tenancy\CurrentMunicipality;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the active municipality (tenant isolation). When a current
 * municipality is set, every query is automatically filtered to it and new
 * records are stamped with it — preventing cross-tenant data leakage by default.
 */
trait BelongsToMunicipality
{
    public static function bootBelongsToMunicipality(): void
    {
        static::addGlobalScope(new MunicipalityScope());

        static::creating(function (Model $model): void {
            $municipalityId = app(CurrentMunicipality::class)->id();
            if ($municipalityId !== null && empty($model->municipality_id)) {
                $model->municipality_id = $municipalityId;
            }
        });
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** Escape hatch for bureau/admin queries that intentionally cross municipalities. */
    public function scopeAcrossAllMunicipalities(Builder $query): Builder
    {
        return $query->withoutGlobalScope(MunicipalityScope::class);
    }
}
