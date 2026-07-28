<?php
declare(strict_types=1);

/**
 * Maps an ISO 4217 code to its display symbol. Falls back to the code
 * itself for anything not in the table, so an unlisted currency still
 * renders (just less prettily) instead of breaking the page.
 */
function currency_symbol(string $isoCode): string {
    $symbols = [
        'GBP' => '£',
        'USD' => '$',
        'EUR' => '€',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'NZD' => 'NZ$',
    ];
    return $symbols[$isoCode] ?? $isoCode . ' ';
}

function format_pence(int $pence, string $isoCode): string {
    return currency_symbol($isoCode) . number_format($pence / 100, 2);
}
