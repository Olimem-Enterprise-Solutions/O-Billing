<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToMunicipality;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A node in the area hierarchy (self-referencing tree). A province has districts,
 * which have towns, which have suburbs. Suburbs (billing-level areas) carry
 * tariffs and customers.
 */
class Area extends Model
{
    use BelongsToMunicipality;
    use HasFactory;

    protected $fillable = [
        'municipality_id', 'area_type_id', 'parent_id', 'name', 'code', 'sage_id',
    ];

    public function areaType(): BelongsTo
    {
        return $this->belongsTo(AreaType::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Area::class, 'parent_id');
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(Tariff::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** Only billing-level areas (suburbs) — where tariffs and customers attach. */
    public function scopeBillingLevel(Builder $query): Builder
    {
        return $query->whereHas('areaType', fn (Builder $q) => $q->where('is_billing_level', true));
    }

    public function isBillingLevel(): bool
    {
        return (bool) $this->areaType?->is_billing_level;
    }

    /** Full path from root, e.g. "Gauteng / Johannesburg / Sandton / Morningside". */
    public function pathLabel(): string
    {
        $labels = [];
        $node = $this;
        while ($node !== null) {
            array_unshift($labels, $node->name);
            $node = $node->parent;
        }

        return implode(' / ', $labels);
    }
}
