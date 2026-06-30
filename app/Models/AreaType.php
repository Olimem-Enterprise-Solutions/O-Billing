<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToMunicipality;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A level in the area hierarchy, e.g. Province (level 1) -> District (2) ->
 * City/Town (3) -> Suburb (4). The deepest, billing level carries tariffs and
 * customers.
 */
class AreaType extends Model
{
    use BelongsToMunicipality;
    use HasFactory;

    protected $fillable = [
        'municipality_id', 'name', 'level', 'is_billing_level',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_billing_level' => 'boolean',
        ];
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }
}
