<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single charge on an invoice. Tenant isolation is inherited via the parent
 * invoice, so no municipality scope is applied directly here.
 */
class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'service_id', 'tr_code', 'description',
        'quantity', 'unit_amount', 'amount', 'tax_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_amount' => 'decimal:6',
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
