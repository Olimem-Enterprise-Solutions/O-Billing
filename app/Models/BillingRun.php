<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToMunicipality;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A billing run generates invoices for all active customers for a given month,
 * so the municipality can see how much it is billing. Totals are kept per
 * currency to support multi-currency municipalities.
 */
class BillingRun extends Model
{
    use BelongsToMunicipality;
    use HasFactory;

    protected $fillable = [
        'municipality_id', 'period_month', 'description', 'status',
        'invoice_count', 'currency_totals', 'run_at',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'currency_totals' => 'array',
            'invoice_count' => 'integer',
            'run_at' => 'datetime',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
