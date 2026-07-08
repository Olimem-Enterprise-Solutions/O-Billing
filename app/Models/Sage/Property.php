<?php

declare(strict_types=1);

namespace App\Models\Sage;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sage `_ccg_EB_Properties` — a rateable stand/erf: its valuation
 * (MarketValue / LandValue / ImprovementValue / LandSize), the `Area` it sits
 * in, the `owner` (a Sage debtor, via OwnerID → Client.DCLink), and the set of
 * services billed on it.
 */
class Property extends SageModel
{
    protected $table = '_ccg_EB_Properties';

    protected function casts(): array
    {
        return [
            'MarketValue' => 'float',
            'LandValue' => 'float',
            'ImprovementValue' => 'float',
            'LandSize' => 'float',
            'Households' => 'integer',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'AreaID');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'OwnerID', 'DCLink');
    }

    public function ratingCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'RatingID');
    }

    public function usageCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'UsageID');
    }

    public function services(): HasMany
    {
        return $this->hasMany(PropertyService::class, 'PropertyID');
    }

    /** The concatenated physical address lines, blanks removed. */
    public function addressLabel(): string
    {
        return collect([
            $this->AddressLine1, $this->AddressLine2, $this->AddressLine3,
            $this->AddressLine4, $this->AddressLine5,
        ])->filter()->implode(', ');
    }
}
