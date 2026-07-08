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

    /**
     * A short, card-friendly money string that won't overflow a stat tile, e.g.
     * "$ 62.8M" / "$ 15.4M" / "$ 517.00". Large values are abbreviated with a
     * K/M/B suffix; use format() when the exact figure is needed.
     */
    public static function compact(float|string|null $amount, ?string $currency): string
    {
        $currency = strtoupper(trim((string) $currency));
        $value = (float) ($amount ?? 0);
        $abs = abs($value);

        [$divisor, $suffix] = match (true) {
            $abs >= 1_000_000_000 => [1_000_000_000, 'B'],
            $abs >= 1_000_000 => [1_000_000, 'M'],
            $abs >= 1_000 => [1_000, 'K'],
            default => [1, ''],
        };

        $number = $suffix === ''
            ? number_format($value, 2)
            : number_format($value / $divisor, 1).$suffix;

        return self::symbol($currency).' '.$number;
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
