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
 * Returns offer items from guillotine and sliding systems merged by system type and dimensions.
 *
 * @param PDO $pdo
 * @param int $offerId
 * @return array<int,array{system:string,width:float,height:float,quantity:int,amount:float}>
 */
function getOfferSystems(PDO $pdo, int $offerId): array
{
    $rows = [];
    try {
        $stmt = $pdo->prepare('SELECT system_type AS system, width, height, quantity, total_amount AS amount FROM guillotinesystems WHERE general_offer_id = :id');
        $stmt->execute([':id' => $offerId]);
        $rows = array_merge($rows, $stmt->fetchAll());
    } catch (Exception $e) {
        // ignore
    }
    try {
        $stmt = $pdo->prepare('SELECT system_type AS system, width, height, quantity, total_amount AS amount FROM slidingsystems WHERE general_offer_id = :id');
        $stmt->execute([':id' => $offerId]);
        $rows = array_merge($rows, $stmt->fetchAll());
    } catch (Exception $e) {
        // ignore
    }
    $merged = [];
    foreach ($rows as $r) {
        $key = strtolower((string)$r['system']) . '|' . $r['width'] . '|' . $r['height'];
        if (!isset($merged[$key])) {
            $merged[$key] = [
                'system'   => $r['system'],
                'width'    => $r['width'],
                'height'   => $r['height'],
                'quantity' => (int)$r['quantity'],
                'amount'   => (float)$r['amount'],
            ];
        } else {
            $merged[$key]['quantity'] += (int)$r['quantity'];
            $merged[$key]['amount'] += (float)$r['amount'];
        }
    }
    return array_values($merged);
}
