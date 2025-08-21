<?php

declare(strict_types=1);

/**
 * Provides product information required for calculations.
 */
interface ProductProviderInterface
{
    /**
     * Return product fields: unit, unit_price, vat_rate, weight_per_meter, category.
     * Return null if product is not found.
     */
    public function getProduct(string $name): ?array;
}

/**
 * Calculates cost breakdown for a guillotine system.
 *
 * @param array{
 *   width: float|int|string,
 *   height: float|int|string,
 *   quantity: int|string,
 *   glass_type?: string,
 *   profit_rate?: float|int|string,
 *   profit_margin?: float|int|string,
 *   provider: ProductProviderInterface
 * } $input
 *
 * @return array{
 *   lines: array<int, array{category:string,name:string,measure:float,unit:string,quantity:float,total:float}>,
 *   totals: array{
 *     alu_cost: float,
 *     glass_cost: float,
 *     extras: array{paint: float, waste: float, labor: float},
 *     base_cost: float,
 *     profit: float,
 *     grand_total: float
 *   },
 *   alu_kg: float
 * }
 */
function calculateGuillotineTotals(array $input): array
{
    if (!isset($input['provider']) || !$input['provider'] instanceof ProductProviderInterface) {
        throw new InvalidArgumentException('Valid product provider is required');
    }
    $provider = $input['provider'];

    $width  = max(0.0, (float) ($input['width'] ?? 0));
    $height = max(0.0, (float) ($input['height'] ?? 0));
    $qty    = max(0, (int) ($input['quantity'] ?? 0));

    if ($width <= 0 || $height <= 0 || $qty <= 0) {
        throw new InvalidArgumentException('Width, height and quantity must be positive');
    }

    $profitRate = (float) ($input['profit_rate'] ?? $input['profit_margin'] ?? 0);

    $glassType = strtolower(str_replace([' ', '-', '_', 'ı'], ['', '', '', 'i'], (string) ($input['glass_type'] ?? '')));
    $includeGlassStrips = $glassType === 'tek' || $glassType === 'tekcam';

    $rules = [
        ['name' => 'Motor Kutusu',       'measure' => fn($w,$h,$q) => $w - 14,                        'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Motor Kapak',        'measure' => fn($w,$h,$q) => $w - 15,                        'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Alt Kasa',           'measure' => fn($w,$h,$q) => $w,                              'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Tutamak',            'measure' => fn($w,$h,$q) => $w - 185,                        'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Kenetli Baza',       'measure' => fn($w,$h,$q) => $w - 185,                        'qty' => fn($w,$h,$q) => 3 * $q],
        ['name' => 'Küpeşte Bazası',     'measure' => fn($w,$h,$q) => $w - 185,                        'qty' => fn($w,$h,$q) => 2 * $q],
        ['name' => 'Küpeşte',           'measure' => fn($w,$h,$q) => $w - 185,                        'qty' => fn($w,$h,$q) => $q],
    ];

    if ($includeGlassStrips) {
        $rules[] = ['name' => 'Yatay Tek Cam Çıtası', 'measure' => fn($w,$h,$q) => ($w - 185) - 52,      'qty' => fn($w,$h,$q) => 11 * $q];
        $rules[] = ['name' => 'Dikey Tek Cam Çıtası', 'measure' => fn($w,$h,$q) => (($h - 290) / 3) - 5, 'qty' => fn($w,$h,$q) => 11 * $q];
    }

    $rules = array_merge($rules, [
        ['name' => 'Dikme',              'measure' => fn($w,$h,$q) => $h - 166,                        'qty' => fn($w,$h,$q) => 2 * $q],
        ['name' => 'Orta Dikme',         'measure' => fn($w,$h,$q) => $h - 166,                        'qty' => fn($w,$h,$q) => 2 * $q],
        ['name' => 'Son Kapatma',        'measure' => fn($w,$h,$q) => $h - (($h - 290) / 3) - 221,      'qty' => fn($w,$h,$q) => 2 * $q],
        ['name' => 'Kanat',              'measure' => fn($w,$h,$q) => ($h - 290) / 3,                   'qty' => fn($w,$h,$q) => 2 * $q],
        ['name' => 'Dikey Baza',         'measure' => fn($w,$h,$q) => ($h - 290) / 3,                   'qty' => fn($w,$h,$q) => 4 * $q],
        ['name' => 'Plastik Set',        'measure' => fn($w,$h,$q) => 1,                                'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Flatbelt Kayış',     'measure' => fn($w,$h,$q) => $h - (($h - 290) / 3) - 221 + 600,'qty' => fn($w,$h,$q) => 2 * $q],
        ['name' => 'Motor Borusu',       'measure' => fn($w,$h,$q) => $w - 75,                          'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Motor Kutu Contası', 'measure' => fn($w,$h,$q) => ($w - 14) * $q + $w * $q,         'qty' => fn($w,$h,$q) => 1],
        ['name' => 'Kanat Contası',      'measure' => fn($w,$h,$q) => (($h - 290) / 3) * $q * 2,        'qty' => fn($w,$h,$q) => 1],
        ['name' => 'Zincir',             'measure' => fn($w,$h,$q) => 1,                                'qty' => fn($w,$h,$q) => $q],
    ]);

    $lines    = [];
    $aluCost  = 0.0;
    $glassCost = 0.0;
    $baseCost = 0.0;
    $aluKg    = 0.0;

    foreach ($rules as $rule) {
        $measure = max(0.0, $rule['measure']($width, $height, $qty));
        $rq      = max(0, $rule['qty']($width, $height, $qty));
        if ($measure <= 0 || $rq <= 0) {
            continue;
        }

        $product = $provider->getProduct($rule['name']);
        if (!$product) {
            continue;
        }

        $unit          = strtolower((string) ($product['unit'] ?? ''));
        $unitPrice     = (float) ($product['unit_price'] ?? 0);
        $vatRate       = (float) ($product['vat_rate'] ?? 0);
        $unitPriceVat  = $unitPrice * (1 + $vatRate / 100);
        $wpm           = (float) ($product['weight_per_meter'] ?? 0);
        $category      = (string) ($product['category'] ?? 'Diğer');

        $qtyDisplay = 0.0;
        $lineTotal  = 0.0;
        $kg         = 0.0;

        switch ($unit) {
            case 'kilogram':
            case 'kg':
            case 'kg/m':
                if ($wpm <= 0) {
                    continue 2;
                }
                $meters     = ($measure / 1000) * $rq;
                $kg         = $meters * $wpm;
                $qtyDisplay = $kg;
                $lineTotal  = $kg * $unitPriceVat;
                break;
            case 'metre':
            case 'm':
                $meters     = ($measure / 1000) * $rq;
                $qtyDisplay = $meters;
                $lineTotal  = $meters * $unitPriceVat;
                break;
            case 'metrekare':
            case 'm²':
            case 'm2':
                $area       = ($width * $height / 1000000) * $rq;
                $qtyDisplay = $area;
                $lineTotal  = $area * $unitPriceVat;
                break;
            default:
                $qtyDisplay = $rq;
                $lineTotal  = $rq * $unitPriceVat;
                break;
        }

        $baseCost += $lineTotal;
        if (strtolower($category) === 'alüminyum') {
            $aluCost += $lineTotal;
            $aluKg   += $kg;
        } elseif (strtolower($category) === 'cam') {
            $glassCost += $lineTotal;
        }

        $lines[] = [
            'category' => $category,
            'name'     => $rule['name'],
            'measure'  => $measure,
            'unit'     => $unit,
            'quantity' => $qtyDisplay,
            'total'    => $lineTotal,
        ];
    }

    $extras = [
        'paint' => $aluKg * 200,
    ];
    $extras['waste'] = ($aluCost + $extras['paint']) * 0.07;
    $area = ($width * $height * $qty) / 1000000; // m²
    $extras['labor'] = $area * 40;

    $baseCost += array_sum($extras);
    $profit     = $baseCost * ($profitRate / 100);
    $grandTotal = $baseCost + $profit;

    $totals = [
        'alu_cost'    => $aluCost,
        'glass_cost'  => $glassCost,
        'extras'      => $extras,
        'base_cost'   => $baseCost,
        'profit'      => $profit,
        'grand_total' => $grandTotal,
    ];

    return [
        'lines'  => $lines,
        'totals' => $totals,
        'alu_kg' => $aluKg,
    ];
}

