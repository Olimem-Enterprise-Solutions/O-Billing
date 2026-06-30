<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\Tenancy\CurrentMunicipality;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filters every query to the active municipality when one is set. Named (not
 * anonymous) so it can be removed via withoutGlobalScope() for deliberate
 * cross-municipality (bureau/admin) queries.
 */
final class MunicipalityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $municipalityId = app(CurrentMunicipality::class)->id();

        if ($municipalityId !== null) {
            $builder->where($model->getTable().'.municipality_id', $municipalityId);
        }
    }
}
