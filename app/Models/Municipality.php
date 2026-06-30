<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A municipality is the tenant. All billing data (areas, services, tariffs,
 * customers, runs, invoices) is isolated per municipality.
 */
class Municipality extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'base_currency', 'supported_currencies', 'tax_rate',
        'tax_label', 'contact_email', 'contact_phone', 'address',
        'setup_completed_at', 'active',
    ];

    protected function casts(): array
    {
        return [
            'supported_currencies' => 'array',
            'tax_rate' => 'decimal:4',
            'setup_completed_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function isSetupComplete(): bool
    {
        return $this->setup_completed_at !== null;
    }

    /** Currencies this municipality bills in (always includes the base). */
    public function currencies(): array
    {
        $list = $this->supported_currencies ?: [];
        if (! in_array($this->base_currency, $list, true)) {
            array_unshift($list, $this->base_currency);
        }

        return array_values(array_unique($list));
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function areaTypes(): HasMany
    {
        return $this->hasMany(AreaType::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function serviceTypes(): HasMany
    {
        return $this->hasMany(ServiceType::class);
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(Tariff::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function billingRuns(): HasMany
    {
        return $this->hasMany(BillingRun::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
