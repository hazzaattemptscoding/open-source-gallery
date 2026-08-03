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

/**
 * The site's configured ISO 4217 code.
 *
 * config.php stores this as a bare top-level string ('currency' => 'GBP'), not
 * nested, and config_store.php maps the admin form's currency.code onto it.
 * Several call sites had drifted to $config['currency']['code'] ?? 'GBP', which
 * reads an offset of a string: PHP returns null, the ?? swallows it, and the
 * site silently behaves as GBP no matter what the operator configured. That is
 * a self-hoster in euros selling credit stamped GBP against orders stamped EUR.
 *
 * One accessor so there is one place to be wrong, and it tolerates the nested
 * shape in case an older config.php in the wild used it.
 */
function config_currency_code(array $config): string {
    $currency = $config['currency'] ?? null;

    if (is_array($currency)) {
        return (string) ($currency['code'] ?? 'GBP');
    }
    if (is_string($currency) && $currency !== '') {
        return $currency;
    }
    return 'GBP';
}
