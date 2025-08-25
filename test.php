<?php

declare(strict_types=1);

const GLASS_UNIT_PRICE = 1680; // TRY per m²

/**
 * Provides product information required for calculations.
 */
interface ProductProviderInterface
{
    /**
     * Return product fields: unit, unit_price, weight_per_meter, category, price_unit.
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
 *   currency?: string,
 *   exchange_rates?: array<string,float>,
 *   provider: ProductProviderInterface
 * } $input
 *
 * @return array{
 *   lines: array<int, array{category:string,name:string,measure:float,unit:string,quantity:float,pieces:int,total:float,currency:string,original_currency:string}>,
 *   totals: array{
 *     alu_cost: float,
 *     glass_cost: float,
 *     aksesuar_cost: float,
 *     fitil_cost: float,
 *     extras: array{paint: float, waste: float, labor: float},
 *     profit: float,
 *     general_expense: float,
 *     grand_total: float
 *   },
 *   currency: string,
 *   alu_kg: float,
 *   alu_painted_kg: float,
 *   alu_fire_kg: float,
 *   glass: array{width: float, height: float, quantity: float}
 * }
 */
function calculateGuillotineTotals(array $input): array
{
    if (!isset($input['provider']) || !$input['provider'] instanceof ProductProviderInterface) {
        throw new InvalidArgumentException('Valid product provider is required');
    }
    $provider = $input['provider'];
    $currency = strtoupper((string) ($input['currency'] ?? 'TRY'));
    $exchangeRates = array_change_key_case($input['exchange_rates'] ?? [], CASE_UPPER);

    $width  = max(0.0, (float) ($input['width'] ?? 0));
    $height = max(0.0, (float) ($input['height'] ?? 0));
    $qty    = max(0, (int) ($input['quantity'] ?? 0));

    if ($width <= 0 || $height <= 0 || $qty <= 0) {
        throw new InvalidArgumentException('Width, height and quantity must be positive');
    }

    $profitRate = (float) ($input['profit_rate'] ?? $input['profit_margin'] ?? 0);

    $glassType = strtolower(str_replace([' ', '-', '_', 'ı'], ['', '', '', 'i'], (string) ($input['glass_type'] ?? '')));
    $includeGlassStrips = $glassType === 'tek' || $glassType === 'tekcam';

    // Glass dimension and quantity calculations
    $verticalBaseMeasure = max(0.0, ($height - 290) / 3);
    $glassWidth  = max(0.0, $width - 221);
    $glassHeight = max(0.0, $verticalBaseMeasure + 25);
    $wingCount   = 2 * $qty;
    $baseCount   = 4 * $qty;
    $glassQty    = ($wingCount + $baseCount) / 2;

    $rules = [
        ['name' => 'Motor Kutusu',       'measure' => fn($w, $h, $q) => $w - 14,                        'qty' => fn($w, $h, $q) => $q],
        ['name' => 'Motor Kapak',        'measure' => fn($w, $h, $q) => $w - 15,                        'qty' => fn($w, $h, $q) => $q],
        ['name' => 'Alt Kasa',           'measure' => fn($w, $h, $q) => $w,                              'qty' => fn($w, $h, $q) => $q],
        ['name' => 'Tutamak',            'measure' => fn($w, $h, $q) => $w - 183,                        'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Kenetli Baza',       'measure' => fn($w, $h, $q) => $w - 183,                        'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Küpeşte Bazası',     'measure' => fn($w, $h, $q) => $w - 183,                        'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Küpeşte',           'measure' => fn($w, $h, $q) => $w - 183,                        'qty' => fn($w, $h, $q) => $q],
    ];

    if ($includeGlassStrips) {
        $rules[] = ['name' => 'Yatay Tek Cam Çıtası', 'measure' => fn($w, $h, $q) => ($w - 185) - 52,      'qty' => fn($w, $h, $q) => 11 * $q];
        $rules[] = ['name' => 'Dikey Tek Cam Çıtası', 'measure' => fn($w, $h, $q) => (($h - 290) / 3) - 6, 'qty' => fn($w, $h, $q) => 11 * $q];
    }

    $rules = array_merge($rules, [
        ['name' => 'Dikme',              'measure' => fn($w, $h, $q) => $h - 166,                        'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Orta Dikme',         'measure' => fn($w, $h, $q) => $h - 166,                        'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Son Kapatma',        'measure' => fn($w, $h, $q) => $h - (($h - 291) / 3) - 214.5,      'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Kanat',              'measure' => fn($w, $h, $q) => ($h - 291) / 3,                   'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Dikey Baza',         'measure' => fn($w, $h, $q) => ($h - 291) / 3,                   'qty' => fn($w, $h, $q) => 4 * $q],
        ['name' => 'Flatbelt Kayış',     'measure' => fn($w, $h, $q) => $h - (($h - 290) / 3) - 221 + 600, 'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Motor Borusu',       'measure' => fn($w, $h, $q) => $w - 75,                          'qty' => fn($w, $h, $q) => $q],
        ['name' => 'Motor Kutu Contası', 'measure' => fn($w, $h, $q) => ($w - 14) * $q + $w * $q,         'qty' => fn($w, $h, $q) => 1],
        ['name' => 'Kanat Contası',      'measure' => fn($w, $h, $q) => (($h - 290) / 3) * $q * 2,        'qty' => fn($w, $h, $q) => 1],
        ['name' => 'Plastik Set',        'measure' => fn($w, $h, $q) => 1,                                'qty' => fn($w, $h, $q) => $q],
        // Zincir unit price was previously treated as 900 TRY via outdated DB data.
        // Force the correct unit price (680 TRY) here to keep calculations consistent
        // even if the database still holds an old value.
        [
            'name'       => 'Zincir',
            'measure'    => fn($w, $h, $q) => 1,
            'qty'        => fn($w, $h, $q) => $q,
            'unit_price' => 680.0,
            'unit'       => 'set',
            'category'   => 'Aksesuar',
        ],
    ]);

    // Glass product rule using calculated dimensions and quantity
    $rules[] = [
        'name'    => 'Cam',
        'measure' => fn($w, $h, $q) => $glassWidth,
        'width'   => fn($w, $h, $q) => $glassWidth,
        'height'  => fn($w, $h, $q) => $glassHeight,
        'qty'     => fn($w, $h, $q) => $glassQty,
    ];

    $lines        = [];
    $aluCost      = 0.0;
    $glassCost    = 0.0;
    $aluKg        = 0.0;
    $aksesuarCost = 0.0;
    $fitilCost    = 0.0;

    foreach ($rules as $rule) {
        $measure    = max(0.0, $rule['measure']($width, $height, $qty));
        $rq         = max(0, (int) $rule['qty']($width, $height, $qty));
        $ruleWidth  = isset($rule['width'])  ? max(0.0, $rule['width']($width, $height, $qty))  : $width;
        $ruleHeight = isset($rule['height']) ? max(0.0, $rule['height']($width, $height, $qty)) : $height;
        if ($measure <= 0 || $rq <= 0) {
            continue;
        }

        if ($rule['name'] === 'Cam') {
            $product = [
                'unit'            => 'm²',
                'unit_price'      => GLASS_UNIT_PRICE,
                'weight_per_meter' => 0,
                'category'        => 'Cam',
                'price_unit'      => 'TRY',
            ];
        } else {
            $product = $provider->getProduct($rule['name']);
            if ($product) {
                if (isset($rule['unit_price'])) {
                    $product['unit_price'] = $rule['unit_price'];
                }
                if (isset($rule['unit'])) {
                    $product['unit'] = $rule['unit'];
                }
                if (isset($rule['category'])) {
                    $product['category'] = $rule['category'];
                }
                if (isset($rule['price_unit'])) {
                    $product['price_unit'] = $rule['price_unit'];
                }
            } elseif (isset($rule['unit_price'])) {
                // Fallback when product row is missing; use data provided in the rule.
                $product = [
                    'unit'            => $rule['unit'] ?? 'adet',
                    'unit_price'      => $rule['unit_price'],
                    'weight_per_meter' => 0,
                    'category'        => $rule['category'] ?? 'Diğer',
                    'price_unit'      => $rule['price_unit'] ?? 'TRY',
                ];
            } else {
                continue;
            }
        }

        $lineCurrency  = strtoupper((string) ($product['price_unit'] ?? 'TRY'));
        $unit          = strtolower((string) ($product['unit'] ?? ''));
        $unitPrice     = (float) ($product['unit_price'] ?? 0);
        $wpm           = (float) ($product['weight_per_meter'] ?? 0);
        $category      = (string) ($product['category'] ?? 'Diğer');

        $originalCurrency = $lineCurrency;
        if ($lineCurrency !== $currency) {
            $rate = $exchangeRates[$lineCurrency] ?? null;
            if ($rate === null) {
                throw new RuntimeException("Exchange rate for {$lineCurrency} to {$currency} not provided");
            }
            $unitPrice *= $rate;
            $lineCurrency = $currency;
        }

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
                $lineTotal  = $kg * $unitPrice;
                break;
            case 'metre':
            case 'm':
                $meters     = ($measure / 1000) * $rq;
                $qtyDisplay = $meters;
                $lineTotal  = $meters * $unitPrice;
                break;
            case 'metrekare':
            case 'm²':
            case 'm2':
                $area       = ($ruleWidth * $ruleHeight / 1000000) * $rq;
                $qtyDisplay = $area;
                $lineTotal  = $area * $unitPrice;
                break;
            default:
                $qtyDisplay = $rq;
                $lineTotal  = $rq * $unitPrice;
                break;
        }

        if (strtolower($category) === 'alüminyum') {
            $aluCost += $lineTotal;
            $aluKg   += $kg;
        } elseif (strtolower($category) === 'cam') {
            $glassCost += $lineTotal;
        } elseif (strtolower($category) === 'aksesuar') {
            $aksesuarCost += $lineTotal;
        } elseif (strtolower($category) === 'fitil') {
            $fitilCost += $lineTotal;
        }

        $lines[] = [
            'category'         => $category,
            'name'             => $rule['name'],
            'measure'          => $measure,
            'unit'             => $unit,
            'quantity'         => $qtyDisplay,
            'pieces'           => $rq,
            'total'            => $lineTotal,
            'currency'         => $lineCurrency,
            'original_currency'=> $originalCurrency,
        ];
    }

    $aluPaintedKg = $aluKg * 1.01;
    $aluFireKg    = $aluPaintedKg * 0.07;

    $extras = [
        'paint'  => $aluPaintedKg * 200,
        // Fire cost calculated per kilogram of aluminum waste
        'waste'  => $aluFireKg * 200,
    ];
    $area = ($width * $height * $qty) / 1000000; // m²
    $extras['labor'] = $area * 40;

    $paintCost       = $extras['paint'] ?? 0.0;
    $fireCost        = $extras['waste'] ?? 0.0;
    $otherExtras     = $extras;
    unset($otherExtras['paint'], $otherExtras['waste']);
    $otherExtrasCost = array_sum($otherExtras);

    // Grand total no longer includes the aluminum cost; it consists only of
    // paint, fire, accessories, seal, glass and other extras costs.
    $grandTotal = $paintCost
        + $fireCost
        + $aksesuarCost
        + $fitilCost
        + $glassCost
        + $otherExtrasCost;

    $profit         = $grandTotal * ($profitRate / 100);
    $totalAmount    = $grandTotal + $profit;
    $generalExpense = $totalAmount * 0.01;
    $finalTotal     = $totalAmount + $generalExpense;

    $totals = [
        'alu_cost'        => $aluCost,
        'glass_cost'      => $glassCost,
        'aksesuar_cost'   => $aksesuarCost,
        'fitil_cost'      => $fitilCost,
        'extras'          => $extras,
        'profit'          => $profit,
        'general_expense' => $generalExpense,
        'grand_total'     => $finalTotal,
    ];

    return [
        'lines'          => $lines,
        'totals'         => $totals,
        'currency'       => $currency,
        'alu_kg'         => $aluKg,
        'alu_painted_kg' => $aluPaintedKg,
        'alu_fire_kg'    => $aluFireKg,
        'glass'          => [
            'width'    => $glassWidth,
            'height'   => $glassHeight,
            'quantity' => $glassQty,
        ],
    ];
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    require __DIR__ . '/header.php';

    function e(?string $v): string
    {
        return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format a numeric value based on its unit.
     * Uses 0 decimals for pieces/mm and 2 decimals for metric units.
     */
    function fmtUnit(float $value, string $unit): string
    {
        $unit = strtolower(trim($unit));
        $twoDecimals = ['metre', 'm', 'kilogram', 'kg', 'kg/m', 'metrekare', 'm²', 'm2'];
        $decimals = in_array($unit, $twoDecimals, true) ? 2 : 0;
        return number_format($value, $decimals, ',', '.');
    }

    function currencySymbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'USD' => '$',
            'EUR' => '€',
            'TRY', 'TL' => '₺',
            default => $currency,
        };
    }

    /**
     * Fetch exchange rates for given currencies relative to a base currency.
     * Returns an array mapping currency code to multiplier for converting
     * prices from that currency to the base currency.
     */
    function fetchExchangeRates(string $base, array $currencies): array
    {
        $base = strtoupper($base);
        // New API does not support limiting symbols, so fetch all and filter.
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
                // API returns how much 1 base currency equals in target currency.
                // We need multiplier from target currency to base, so take reciprocal.
                $rates[$curUpper] = 1 / $rate;
            }
        }
        return $rates;
    }

    class PdoProductProvider implements ProductProviderInterface
    {
        public function __construct(private PDO $pdo) {}

        public function getProduct(string $name): ?array
        {
            $stmt = $this->pdo->prepare('SELECT p.unit, p.unit_price, p.weight_per_meter, p.price_unit, c.name AS category FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE LOWER(p.name) = LOWER(:name)');
            $stmt->execute([':name' => $name]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        }
    }

    $id = filter_input(INPUT_GET, 'quote_id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo '<div class="container mt-4"><div class="alert alert-danger">Geçersiz giyotin.</div></div>';
        require __DIR__ . '/footer.php';
        exit;
    }

    $stmt = $pdo->prepare('SELECT width, height, quantity, glass_type, profit_rate, profit_margin FROM guillotinesystems WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo '<div class="container mt-4"><div class="alert alert-danger">Giyotin satırı bulunamadı.</div></div>';
        require __DIR__ . '/footer.php';
        exit;
    }

    $provider = new PdoProductProvider($pdo);

    $exchangeRates = fetchExchangeRates('TRY', ['USD', 'EUR']);

    try {
        $result = calculateGuillotineTotals([
            'width'         => $row['width'],
            'height'        => $row['height'],
            'quantity'      => $row['quantity'],
            'glass_type'    => $row['glass_type'] ?? '',
            'profit_rate'   => $row['profit_rate'] ?? ($row['profit_margin'] ?? 0),
            'currency'      => 'TRY',
            'exchange_rates'=> $exchangeRates,
            'provider'      => $provider,
        ]);
    } catch (Throwable $e) {
        echo '<div class="container mt-4"><div class="alert alert-danger">Hesaplama hatası: ' . e($e->getMessage()) . '</div></div>';
        require __DIR__ . '/footer.php';
        exit;
    }

    echo '<div class="container mt-4">';
    echo '<h3>Kalemler</h3>';

    $categories = [];
    foreach ($result['lines'] as $line) {
        if (strtolower($line['category']) === 'cam') {
            continue;
        }
        $key = strtolower($line['category']);
        if (!isset($categories[$key])) {
            $categories[$key] = ['title' => $line['category'], 'lines' => []];
        }
        $categories[$key]['lines'][] = $line;
    }

    $tot       = $result['totals'];
    $glassInfo = $result['glass'] ?? null;
    $currencySymbol = currencySymbol($result['currency']);

    foreach ($categories as $cat) {
        $isAlu = strcasecmp($cat['title'], 'Alüminyum') === 0;
        echo '<h5>' . e($cat['title']) . '</h5>';
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm table-striped mb-3">';
        echo '<thead><tr><th>Ad</th><th>Ölçü (mm)</th>';
        if ($isAlu) {
            echo '<th>Adet</th>';
        }
        echo '<th>Miktar</th><th>Birim</th><th class="text-end">Tutar</th></tr></thead><tbody>';
        $qtySum   = 0.0;
        $totalSum = 0.0;
        $unit     = '';
        $pieceSum = 0;
        foreach ($cat['lines'] as $line) {
            echo '<tr>';
            echo '<td>' . e($line['name']) . '</td>';
            echo '<td>' . e(number_format($line['measure'], 0, ',', '.')) . '</td>';
            if ($isAlu) {
                echo '<td>' . e(number_format((int) ($line['pieces'] ?? 0), 0, ',', '.')) . '</td>';
            }
            echo '<td>' . e(fmtUnit($line['quantity'], $line['unit'])) . '</td>';
            echo '<td>' . e($line['unit']) . '</td>';
            echo '<td class="text-end">' . e(number_format($line['total'], 2, ',', '.')) . ' ' . e(currencySymbol($line['currency'])) . '</td>';
            echo '</tr>';
            $qtySum   += $line['quantity'];
            $totalSum += $line['total'];
            if ($isAlu) {
                $pieceSum += (int) ($line['pieces'] ?? 0);
            }
            if ($unit === '') {
                $unit = $line['unit'];
            } elseif ($unit !== $line['unit']) {
                $unit = '';
            }
        }
        echo '<tr>';
        echo '<td colspan="2" class="text-end"><strong>Toplam</strong></td>';
        if ($isAlu) {
            echo '<td>' . e(number_format($pieceSum, 0, ',', '.')) . '</td>';
        }
        echo '<td>' . e(fmtUnit($qtySum, $unit)) . '</td>';
        echo '<td>' . e($unit) . '</td>';
        echo '<td class="text-end">' . e(number_format($totalSum, 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
        echo '</tr>';
        echo '</tbody></table></div>';
    }

    if ($glassInfo && $glassInfo['quantity'] > 0) {
        $singleArea = ($glassInfo['width'] * $glassInfo['height']) / 1000000;
        $totalArea  = $singleArea * $glassInfo['quantity'];
        echo '<h5>Cam</h5>';
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm table-striped mb-3">';
        echo '<thead><tr><th>Genişlik (mm)</th><th>Yükseklik (mm)</th><th>Adet</th><th>Birim m²</th><th>Toplam m²</th><th class="text-end">Tutar</th></tr></thead><tbody>';
        echo '<tr>';
        echo '<td>' . e(number_format($glassInfo['width'], 0, ',', '.')) . '</td>';
        echo '<td>' . e(number_format($glassInfo['height'], 0, ',', '.')) . '</td>';
        echo '<td>' . e(number_format($glassInfo['quantity'], 0, ',', '.')) . '</td>';
        echo '<td>' . e(number_format($singleArea, 2, ',', '.')) . '</td>';
        echo '<td>' . e(number_format($totalArea, 2, ',', '.')) . '</td>';
        echo '<td class="text-end">' . e(number_format($tot['glass_cost'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<td colspan="3" class="text-end"><strong>Toplam</strong></td>';
        echo '<td></td>';
        echo '<td>' . e(number_format($totalArea, 2, ',', '.')) . '</td>';
        echo '<td class="text-end">' . e(number_format($tot['glass_cost'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
        echo '</tr>';
        echo '</tbody></table></div>';
    }
    echo '<div class="mt-3">';
    echo '<table class="table table-bordered table-sm">';
    echo '<tbody>';

    $grandTotalWithProfit = $tot['grand_total'];

    echo '<tr><th>Alüminyum Boyalı ' . e(number_format($result['alu_painted_kg'], 2, ',', '.')) . ' kg</th><td>'
        . e(number_format($tot['extras']['paint'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td></tr>';
    echo '<tr><th>Alüminyum Fire ' . e(number_format($result['alu_fire_kg'], 2, ',', '.')) . ' kg</th><td>'
        . e(number_format($tot['extras']['waste'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td></tr>';
    echo '<tr><th>Aksesuar</th><td>' . e(number_format($tot['aksesuar_cost'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td></tr>';
    echo '<tr><th>Fitil</th><td>' . e(number_format($tot['fitil_cost'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td></tr>';
    echo '<tr><th>İmalat İşçiliği</th><td>' . e(number_format($tot['extras']['labor'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td></tr>';

    echo '<tr class="table-light fw-bold">';
    echo '<td>Kâr</td><td>' . e(number_format($tot['profit'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
    echo '</tr>';
    echo '<tr><th>Genel Gider</th><td>' . e(number_format($tot['general_expense'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td></tr>';

    echo '<tr class="table-success fw-bold">';
    echo '<td>Genel Toplam</td><td>' . e(number_format($grandTotalWithProfit, 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
    echo '</tr>';

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';

    require __DIR__ . '/footer.php';
}
