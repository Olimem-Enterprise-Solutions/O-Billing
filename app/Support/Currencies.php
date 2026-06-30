<?php

declare(strict_types=1);

namespace App\Support;

use Brick\Money\Money;

/**
 * Curated currency list and money formatting helpers for the multi-currency
 * billing engine. Backed by brick/money for correct minor-unit rounding.
 */
final class Currencies
{
    /** Currencies offered in setup, with display labels. */
    public const OPTIONS = [
        'ZAR' => 'ZAR — South African Rand',
        'USD' => 'USD — US Dollar',
        'EUR' => 'EUR — Euro',
        'GBP' => 'GBP — Pound Sterling',
        'BWP' => 'BWP — Botswana Pula',
        'NAD' => 'NAD — Namibian Dollar',
        'ZWG' => 'ZWG — Zimbabwe Gold',
        'KES' => 'KES — Kenyan Shilling',
        'NGN' => 'NGN — Nigerian Naira',
        'GHS' => 'GHS — Ghanaian Cedi',
    ];

    /** Symbols for quick display (fallback to the ISO code). */
    private const SYMBOLS = [
        'ZAR' => 'R', 'USD' => '$', 'EUR' => '€', 'GBP' => '£',
        'BWP' => 'P', 'NAD' => 'N$', 'ZWG' => 'ZWG', 'KES' => 'KSh',
        'NGN' => '₦', 'GHS' => 'GH₵',
    ];

    public static function symbol(string $currency): string
    {
        return self::SYMBOLS[$currency] ?? $currency.' ';
    }

    /** Format a numeric amount as a money string, e.g. "R 1,234.56". */
    public static function format(float|string|null $amount, ?string $currency): string
    {
        $currency = strtoupper(trim((string) $currency));
        $value = (float) ($amount ?? 0);

        // Use brick/money for correct minor-unit rounding when the ISO currency
        // is recognised; otherwise fall back to a plain formatted number so an
        // unknown/blank code can never 500 the page.
        try {
            if ($currency !== '') {
                $value = Money::of((string) $value, $currency, roundingMode: \Brick\Math\RoundingMode::HALF_UP)
                    ->getAmount()->toFloat();
            }
        } catch (\Brick\Money\Exception\UnknownCurrencyException) {
            // keep the raw value
        }

        return self::symbol($currency).' '.number_format($value, 2);
    }
}
