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
 * @return array<string,float>
 */
function fetchExchangeRates(string $base, array $currencies): array
{
    $base = strtoupper($base);
    $json = @file_get_contents("https://open.er-api.com/v6/latest/{$base}");
    $data = $json ? json_decode($json, true) : null;
    if (!is_array($data) || (($data['result'] ?? '') !== 'success') || empty($data['rates'])) {
        return [];
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
    return $rates;
}

/**
 * Determines whether the given offer has expired.
 *
 * @param array $offer Offer row including status and valid_until fields.
 */
function isExpired(array $offer): bool
{
    $status = strtolower((string)($offer['status'] ?? ''));
    if ($status === 'expired') {
        return true;
    }
    $validUntil = $offer['valid_until'] ?? null;
    if ($validUntil && strtotime((string)$validUntil) < time()) {
        return true;
    }
    return false;
}
