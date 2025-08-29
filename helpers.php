<?php
declare(strict_types=1);

/**
 * Fetches exchange rates for the given currencies relative to the base.
 *
 * The external API returns how much 1 unit of the base currency equals in the
 * target currencies. This function returns multipliers from target currency to
 * the base currency (i.e. reciprocal of API rate).
 *
 * @param string $base       Base currency code.
 * @param array  $currencies Target currency codes.
 *
 * @return array<string,float>|null Returns null if rates couldn't be fetched.
 */
function fetchExchangeRates(string $base, array $currencies): ?array
{
    $base = strtoupper($base);
    $json = @file_get_contents("https://open.er-api.com/v6/latest/{$base}");
    if ($json === false) {
        throw new RuntimeException('Exchange rate API request failed.');
    }
    $data = json_decode($json, true);
    if (!is_array($data) || (($data['result'] ?? '') !== 'success') || empty($data['rates'])) {
        throw new RuntimeException('Invalid exchange rate API response.');
    }
    $rates = [];
    foreach ($currencies as $cur) {
        $curUpper = strtoupper($cur);
        if ($curUpper === $base) {
            $rates[$curUpper] = 1.0;
            continue;
        }
        $rate = $data['rates'][$curUpper] ?? null;
        if ($rate && $rate > 0) {
            $rates[$curUpper] = 1 / $rate;
        }
    }
    return $rates ?: null;
}
