<?php
function calculateGuillotineCategoryTotals(PDO $pdo, array $row): array
{
    $width  = max(0, (float)($row['width'] ?? 0));
    $height = max(0, (float)($row['height'] ?? 0));
    $qty    = max(0, (int)($row['quantity'] ?? 0));

    // Helper to normalize glass type into three categories
    if (!function_exists('classifyGlassType')) {
        function classifyGlassType(?string $glassType): string
        {
            $gt = strtolower(trim((string)$glassType));
            $gt = str_replace([' ', '-', '_', 'ı'], ['', '', '', 'i'], $gt);
            if ($gt === 'tek' || $gt === 'tekcam') {
                return 'tek';
            }
            if (str_contains($gt, 'isicam')) {
                return 'ısıcam';
            }
            return 'other';
        }
    }

    $glassType = classifyGlassType($row['glass_type'] ?? '');
    $includeGlassStrips = $glassType === 'tek';

    // Base rules common to all glass types
    $rules = [
        ['name' => 'Motor Kutusu',        'measure' => fn($w,$h,$q) => $w - 14,                        'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Motor Kapak',         'measure' => fn($w,$h,$q) => $w - 15,                        'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Alt Kasa',            'measure' => fn($w,$h,$q) => $w,                              'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Tutamak',             'measure' => fn($w,$h,$q) => $w - 185,                        'qty' => fn($w,$h,$q) => $q],
        ['name' => 'Kenetli Baza',        'measure' => fn($w,$h,$q) => $w - 185,                        'qty' => fn($w,$h,$q) => 3*$q],
        ['name' => 'Küpeşte Bazası',      'measure' => fn($w,$h,$q) => $w - 185,                        'qty' => fn($w,$h,$q) => 2*$q],
        ['name' => 'Küpeşte',             'measure' => fn($w,$h,$q) => $w - 185,                        'qty' => fn($w,$h,$q) => $q],
    ];

    // Include glass strips only for single glass systems
    if ($includeGlassStrips) {
        $rules[] = ['name' => 'Yatay Tek Cam Çıtası', 'measure' => fn($w,$h,$q) => ($w - 185) - 52,      'qty' => fn($w,$h,$q) => 11*$q];
        $rules[] = ['name' => 'Dikey Tek Cam Çıtası', 'measure' => fn($w,$h,$q) => (($h - 290) / 3) - 5, 'qty' => fn($w,$h,$q) => 11*$q];
    }

    // Remaining universal rules
    $rules[] = ['name' => 'Dikme',               'measure' => fn($w,$h,$q) => $h - 166,                        'qty' => fn($w,$h,$q) => 2*$q];
    $rules[] = ['name' => 'Orta Dikme',          'measure' => fn($w,$h,$q) => $h - 166,                        'qty' => fn($w,$h,$q) => 2*$q];
    $rules[] = ['name' => 'Son Kapatma',         'measure' => fn($w,$h,$q) => $h - (($h - 290)/3) - 221,       'qty' => fn($w,$h,$q) => 2*$q];
    $rules[] = ['name' => 'Kanat',               'measure' => fn($w,$h,$q) => ($h - 290) / 3,                  'qty' => fn($w,$h,$q) => 2*$q];
    $rules[] = ['name' => 'Dikey Baza',          'measure' => fn($w,$h,$q) => ($h - 290) / 3,                  'qty' => fn($w,$h,$q) => 4*$q];
    // Eski Zincir ürününü Plastik Set olarak değiştir
    $rules[] = ['name' => 'Plastik Set',         'measure' => fn($w,$h,$q) => 1,                               'qty' => fn($w,$h,$q) => $q];
    $rules[] = ['name' => 'Flatbelt Kayış',      'measure' => fn($w,$h,$q) => $h - (($h - 290)/3) - 221 + 600, 'qty' => fn($w,$h,$q) => 2*$q];
    $rules[] = ['name' => 'Motor Borusu',        'measure' => fn($w,$h,$q) => $w - 75,                         'qty' => fn($w,$h,$q) => $q];
    $rules[] = ['name' => 'Motor Kutu Contası',  'measure' => fn($w,$h,$q) => ($w - 14)*$q + $w*$q,            'qty' => fn($w,$h,$q) => 1];
    $rules[] = ['name' => 'Kanat Contası',       'measure' => fn($w,$h,$q) => (($h - 290)/3)*$q*2,             'qty' => fn($w,$h,$q) => 1];
    // Yeni Zincir ürününü ekle
    $rules[] = ['name' => 'Zincir',              'measure' => fn($w,$h,$q) => 1,                               'qty' => fn($w,$h,$q) => $q];

    $pStmt = $pdo->prepare('SELECT unit, unit_price, vat_rate, weight_per_meter, category FROM products WHERE LOWER(name) = LOWER(:name)');

    $categoryTotals   = [];
    $categoryQtyTotals = [];
    $items            = [];
    $base             = 0.0;
    $aluminumKg       = 0.0;

    foreach ($rules as $rule) {
        $measure = max(0, $rule['measure']($width, $height, $qty));
        $rq      = max(0, $rule['qty']($width, $height, $qty));
        if ($measure <= 0 || $rq <= 0) {
            continue;
        }
        if (in_array($rule['name'], ['Plastik Set', 'Zincir'], true)) {
            $unitPrice  = $rule['name'] === 'Plastik Set' ? 1680 : 840;
            $qtyDisplay = $rq;
            $lineTotal  = $qtyDisplay * $unitPrice;
            $cat        = 'Aksesuar';
            $base += $lineTotal;
            $categoryTotals[$cat]    = ($categoryTotals[$cat] ?? 0) + $lineTotal;
            $categoryQtyTotals[$cat] = ($categoryQtyTotals[$cat] ?? 0) + $qtyDisplay;
            $items[] = [
                'category' => $cat,
                'name'     => $rule['name'],
                'measure'  => null,
                'unit'     => 'set',
                'quantity' => $qtyDisplay,
                'total'    => $lineTotal,
            ];
            continue;
        }
        $pStmt->execute([':name' => $rule['name']]);
        if ($p = $pStmt->fetch(PDO::FETCH_ASSOC)) {
            $unitPrice    = (float)($p['unit_price'] ?? 0);
            $vatRate      = (float)($p['vat_rate'] ?? 0);
            $unitPriceVat = $unitPrice * (1 + $vatRate / 100);
            $unitRaw      = (string)($p['unit'] ?? '');
            $unit         = strtolower($unitRaw);
            $lineTotal    = 0.0;
            $qtyDisplay   = 0.0;
            $kg           = 0.0;
            switch ($unit) {
                case 'kilogram':
                case 'kg':
                case 'kg/m':
                    $wpm = (float)($p['weight_per_meter'] ?? 0);
                    if ($wpm <= 0) {
                        continue 2;
                    }
                    $meters    = ($measure / 1000) * $rq;
                    $kg        = $meters * $wpm;
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
            }
            $cat = trim((string)($p['category'] ?? ''));
            $cat = $cat !== '' ? $cat : 'Diğer';
            if (in_array($cat, ['Alüminyum', 'Alüminyum Boyalı', 'Alüminyum Fire'], true)) {
                if ($kg <= 0) {
                    $wpm = (float)($p['weight_per_meter'] ?? 0);
                    if ($wpm > 0 && ($unit === 'm' || $unit === 'metre')) {
                        $meters     = ($measure / 1000) * $rq;
                        $kg         = $meters * $wpm;
                        $qtyDisplay = $kg;
                    }
                }
                if ($cat === 'Alüminyum') {
                    $kg        *= 1.1;
                    $qtyDisplay = $kg;
                }
                $lineTotal = $qtyDisplay * 200;
            }
            $base += $lineTotal;
            $categoryTotals[$cat]    = ($categoryTotals[$cat] ?? 0) + $lineTotal;
            $categoryQtyTotals[$cat] = ($categoryQtyTotals[$cat] ?? 0) + $qtyDisplay;
            if (strtolower($cat) === 'alüminyum') {
                $aluminumKg += $kg;
            }
            $items[] = [
                'category' => $cat,
                'name'     => $rule['name'],
                'measure'  => $measure,
                'unit'     => $unitRaw,
                'quantity' => $qtyDisplay,
                'total'    => $lineTotal,
            ];
        }
    }

    $camWidth        = max(0, $width - 221);
    $verticalBase    = max(0, ($height - 290) / 3);
    $camHeight       = max(0, $verticalBase + 28);
    $kanatAdet       = 2 * $qty;
    $dikeyBazaAdet   = 4 * $qty;
    $camAdet         = ($kanatAdet + $dikeyBazaAdet) / 2;
    $camAreaPerPiece = ($camWidth * $camHeight) / 1000000;
    $camAreaTotal    = $camAreaPerPiece * $camAdet;
    $camTotal        = $camAreaTotal * 1680;
    $base           += $camTotal;
    $categoryTotals['Cam']    = ($categoryTotals['Cam'] ?? 0) + $camTotal;
    $categoryQtyTotals['Cam'] = ($categoryQtyTotals['Cam'] ?? 0) + $camAreaTotal;
    $items[] = [
        'category' => 'Cam',
        'name'     => 'Cam',
        'measure'  => null,
        'unit'     => 'm²',
        'quantity' => $camAreaTotal,
        'width'    => $camWidth,
        'height'   => $camHeight,
        'count'    => $camAdet,
        'area'     => $camAreaTotal,
        'total'    => $camTotal,
    ];

    $kgPainted   = $aluminumKg * 1.01;
    $alPaintCost = $kgPainted * 200;
    $alWasteQty  = $kgPainted * 0.07;
    $alWasteCost = $alWasteQty * 200;
    $base += $alPaintCost + $alWasteCost;
    $categoryTotals['Alüminyum Boyalı']    = ($categoryTotals['Alüminyum Boyalı'] ?? 0) + $alPaintCost;
    $categoryQtyTotals['Alüminyum Boyalı'] = ($categoryQtyTotals['Alüminyum Boyalı'] ?? 0) + $kgPainted;
    $categoryTotals['Alüminyum Fire']     = ($categoryTotals['Alüminyum Fire'] ?? 0) + $alWasteCost;
    $categoryQtyTotals['Alüminyum Fire']  = ($categoryQtyTotals['Alüminyum Fire'] ?? 0) + $alWasteQty;
    $items[] = [
        'category' => 'Alüminyum Boyalı',
        'name'     => 'Alüminyum Boyalı',
        'measure'  => null,
        'unit'     => 'kg',
        'quantity' => $kgPainted,
        'total'    => $alPaintCost,
    ];
    $items[] = [
        'category' => 'Alüminyum Fire',
        'name'     => 'Alüminyum Fire',
        'measure'  => null,
        'unit'     => 'kg',
        'quantity' => $alWasteQty,
        'total'    => $alWasteCost,
    ];

    $area      = ($width * $height * $qty) / 1000000;
    $laborCost = $area * 40;
    $base     += $laborCost;
    $categoryTotals['İşçilik']    = ($categoryTotals['İşçilik'] ?? 0) + $laborCost;
    $categoryQtyTotals['İşçilik'] = ($categoryQtyTotals['İşçilik'] ?? 0) + $area;
    $items[] = [
        'category' => 'İşçilik',
        'name'     => 'İşçilik',
        'measure'  => null,
        'unit'     => 'm²',
        'quantity' => $area,
        'total'    => $laborCost,
    ];

    $rate   = (float)($row['profit_rate'] ?? $row['profit_margin'] ?? 0);
    $profit = $base * ($rate / 100);
    $total  = $base + $profit;
    $categoryTotals['Diğer']    = ($categoryTotals['Diğer'] ?? 0) + $profit;
    $categoryQtyTotals['Diğer'] = ($categoryQtyTotals['Diğer'] ?? 0) + 1;
    $items[] = [
        'category' => 'Diğer',
        'name'     => 'Kâr',
        'measure'  => null,
        'unit'     => '',
        'quantity' => 1,
        'total'    => $profit,
    ];

    return [
        'categories' => $categoryTotals,
        'quantities' => $categoryQtyTotals,
        'items'      => $items,
        'profit'     => $profit,
        'total'      => $total,
    ];
}
