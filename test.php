<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

//
// Cam Birim Fiyatı
// Cam fiyatı artık veritabanındaki ürün bilgilerinden alınır.
//

//
// Ürün Sağlayıcı Arayüzü
// Hesaplamalarda kullanılacak ürün bilgilerini sağlayacak yöntemleri tanımlar.
//
/**
 * Provides product information required for calculations.
 */
interface ProductProviderInterface
{
    //
    // Ürün Bilgisi Alma
    // Verilen isimdeki ürünü bulur; bulunmazsa null döner.
    //
    /**
     * Return product fields: unit, unit_price, weight_per_meter, category, price_unit.
     * Return null if product is not found.
     */
    public function getProduct(string $name): ?array;
}

//
// Giyotin Hesaplayıcı Fonksiyonu
// Sistem parçalarının maliyetlerini hesaplayarak detaylı bir özet döndürür.
//
/**
 * Calculates cost breakdown for a guillotine system.
 *
 * @param array{
 *   width: float|int|string,
 *   height: float|int|string,
 *   quantity: int|string,
 *   glass_type?: string,
 *   profit_rate?: float|int|string,
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
 *     general_expense: float,
 *     profit: float,
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
    //
    // Sağlayıcı Doğrulaması
    // Hesaplama için geçerli bir ürün sağlayıcı nesnesi gereklidir.
    //
    if (!isset($input['provider']) || !$input['provider'] instanceof ProductProviderInterface) {
        throw new InvalidArgumentException('Valid product provider is required');
    }

    //
    // Sağlayıcı ve Para Birimi
    // Kullanılacak ürün sağlayıcıyı ve para birimini girişten alır.
    //
    $provider = $input['provider'];
    $currency = strtoupper((string) ($input['currency'] ?? 'TRY'));
    $exchangeRates = array_change_key_case($input['exchange_rates'] ?? [], CASE_UPPER);

    //
    // Temel Ölçüler
    // Genişlik, yükseklik ve adet değerlerini sayısal ve pozitif olacak şekilde hazırlar.
    //
    $width  = max(0.0, (float) ($input['width'] ?? 0));
    $height = max(0.0, (float) ($input['height'] ?? 0));
    $qty    = max(0, (int) ($input['quantity'] ?? 0));

    //
    // Ölçü Doğrulaması
    // Negatif veya sıfır değerler hatalı olduğundan işlem durdurulur.
    //
    if ($width <= 0 || $height <= 0 || $qty <= 0) {
        throw new InvalidArgumentException('Width, height and quantity must be positive');
    }

    //
    // Cam Türü
    // Cam türüne göre ek cam çıtası hesaplanıp hesaplanmayacağını belirler.
    //
    $glassType = strtolower(str_replace([' ', '-', '_', 'ı'], ['', '', '', 'i'], (string) ($input['glass_type'] ?? '')));
    $includeGlassStrips = $glassType === 'tek' || $glassType === 'tekcam';

    //
    // Cam Ölçüleri Hesabı
    // Cam genişliği, yüksekliği ve adetini giriş ölçülerine göre hesaplar.
    //
    $verticalBaseMeasure = max(0.0, ($height - 290) / 3);
    $glassWidth  = max(0.0, $width - 221);
    $glassHeight = max(0.0, $verticalBaseMeasure + 25);
    $wingCount   = 2 * $qty;
    $baseCount   = 4 * $qty;
    $glassQty    = ($wingCount + $baseCount) / 2;

    //
    // Temel Parça Kuralları
    // Giyotin sisteminde kullanılacak ana parçaların ölçü ve adet hesaplamalarını içerir.
    //
    $rules = [
        ['name' => 'Motor Kutusu',       'measure' => fn($w, $h, $q) => $w - 14,                        'qty' => fn($w, $h, $q) => $q],
        ['name' => 'Motor Kapak',        'measure' => fn($w, $h, $q) => $w - 15,                        'qty' => fn($w, $h, $q) => $q],
        ['name' => 'Alt Kasa',           'measure' => fn($w, $h, $q) => $w,                              'qty' => fn($w, $h, $q) => $q],
        ['name' => 'Tutamak',            'measure' => fn($w, $h, $q) => $w - 183,                        'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Kenetli Baza',       'measure' => fn($w, $h, $q) => $w - 183,                        'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Küpeşte Bazası',     'measure' => fn($w, $h, $q) => $w - 183,                        'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Küpeşte',           'measure' => fn($w, $h, $q) => $w - 183,                        'qty' => fn($w, $h, $q) => $q],
    ];

    //
    // Cam Çıtası Ekleri
    // Tek cam kullanıldığında yatay ve dikey çıtalar listeye eklenir.
    //
    if ($includeGlassStrips) {
        $rules[] = ['name' => 'Yatay Tek Cam Çıtası', 'measure' => fn($w, $h, $q) => ($w - 185) - 52,      'qty' => fn($w, $h, $q) => 11 * $q];
        $rules[] = ['name' => 'Dikey Tek Cam Çıtası', 'measure' => fn($w, $h, $q) => (($h - 290) / 3) - 6, 'qty' => fn($w, $h, $q) => 11 * $q];
    }

    //
    // Ek Parça Kuralları
    // Diğer tüm profil ve aksesuarların ölçü hesapları bu listeye eklenir.
    //
    $rules = array_merge($rules, [
        ['name' => 'Dikme',              'measure' => fn($w, $h, $q) => $h - 166,                        'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Orta Dikme',         'measure' => fn($w, $h, $q) => $h - 166,                        'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Son Kapatma',        'measure' => fn($w, $h, $q) => $h - (($h - 291) / 3) - 214.5,      'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Kanat',              'measure' => fn($w, $h, $q) => ($h - 291) / 3,                   'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Dikey Baza',         'measure' => fn($w, $h, $q) => ($h - 291) / 3,                   'qty' => fn($w, $h, $q) => 4 * $q],
        ['name' => 'Flatbelt Kayış',     'measure' => fn($w, $h, $q) => $h - (($h - 290) / 3) - 221 + 600, 'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Motor Borusu',       'measure' => fn($w, $h, $q) => $w - 75,                          'qty' => fn($w, $h, $q) => $q],
        ['name' => 'Motor Kutu Contası', 'measure' => fn($w, $h, $q) => ($w - 14) * $q + $w * $q,         'qty' => fn($w, $h, $q) => 1],
        ['name' => 'Kanat Contası',      'measure' => fn($w, $h, $q) => ((($h - 291) / 3) * 2) * 2 * $q,  'qty' => fn($w, $h, $q) => 1],
        ['name' => 'Plastik Set',        'measure' => fn($w, $h, $q) => 1,                                'qty' => fn($w, $h, $q) => $q],
        ['name' => 'Zincir',       'measure'    => fn($w, $h, $q) => 1,                             'qty'        => fn($w, $h, $q) => $q],
    ]);

    //
    // Cam Ürünü Kuralı
    // Önceden hesaplanan cam ölçülerini kullanarak cam malzemesini listeye ekler.
    //
    $rules[] = [
        'name'    => 'Cam',
        'measure' => fn($w, $h, $q) => $glassWidth,
        'width'   => fn($w, $h, $q) => $glassWidth,
        'height'  => fn($w, $h, $q) => $glassHeight,
        'qty'     => fn($w, $h, $q) => $glassQty,
    ];

    //
    // Sonuç Biriktiricileri
    // Hesaplama sırasında kullanılan satırlar ve maliyet toplamlarını başlatır.
    //
    $lines        = [];
    $aluCost      = 0.0;
    $glassCost    = 0.0;
    $aluKg        = 0.0;
    $aksesuarCost = 0.0;
    $fitilCost    = 0.0;

    //
    // Kural Döngüsü
    // Her parça kuralını hesaplayarak sonuç listesine ekler.
    //
    foreach ($rules as $rule) {
        //
        // Ölçü ve Adet Hesabı
        // Her kural için ölçüleri hesaplayıp geçerli olup olmadığını kontrol eder.
        //
        $measure    = max(0.0, $rule['measure']($width, $height, $qty));
        $rq         = max(0, (int) $rule['qty']($width, $height, $qty));
        $ruleWidth  = isset($rule['width'])  ? max(0.0, $rule['width']($width, $height, $qty))  : $width;
        $ruleHeight = isset($rule['height']) ? max(0.0, $rule['height']($width, $height, $qty)) : $height;
        if ($measure <= 0 || $rq <= 0) {
            continue;
        }

        //
        // Ürün Bilgisi Seçimi
        // Ürün veritabanından alınır; gerekli alanlar kuraldan güncellenir.
        //
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
            //
            // Ürün Bulunamadı
            // Veritabanında ürün yoksa kuraldaki bilgiler kullanılır.
            //
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

        //
        // Para Birimi ve Birim Bilgileri
        // Ürünün fiyat birimini, ölçü birimini ve kategorisini hazırlar.
        //
        $lineCurrency  = strtoupper((string) ($product['price_unit'] ?? 'TRY'));
        $unit          = strtolower((string) ($product['unit'] ?? ''));
        $unitPrice     = (float) ($product['unit_price'] ?? 0);
        $wpm           = (float) ($product['weight_per_meter'] ?? 0);
        $category      = (string) ($product['category'] ?? 'Diğer');

        //
        // Kur Dönüşümü
        // Ürün para birimi hedef para biriminden farklıysa kur çarpanı uygulanır.
        //
        $originalCurrency = $lineCurrency;
        if ($lineCurrency !== $currency) {
            $rate = $exchangeRates[$lineCurrency] ?? null;
            if ($rate === null) {
                throw new RuntimeException("Exchange rate for {$lineCurrency} to {$currency} not provided");
            }
            $unitPrice *= $rate;
            $lineCurrency = $currency;
        }

        //
        // Miktar ve Toplam
        // Ürünün ölçü birimine göre miktar ve toplam tutar hesaplanır.
        //
        $qtyDisplay = 0.0;
        $lineTotal  = 0.0;
        $kg         = 0.0;

        //
        // Birim Dönüşümü
        // Farklı birim türleri için uygun hesaplama yapılır.
        //
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

        //
        // Kategoriye Göre Toplama
        // Hesaplanan tutarları kategori bazlı toplamlara ekler.
        //
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

        //
        // Satır Kaydı
        // Hesaplanan değerleri çıktı listesine ekler.
        //
        $lines[] = [
            'category'         => $category,
            'name'             => $rule['name'],
            'measure'          => $measure,
            'unit'             => $unit,
            'quantity'         => $qtyDisplay,
            'pieces'           => $rq,
            'total'            => $lineTotal,
            'currency'         => $lineCurrency,
            'original_currency' => $originalCurrency,
        ];
    }

    //
    // Alüminyum Ağırlıkları
    // Boyama ve fire oranlarına göre alüminyum ağırlıkları hesaplanır.
    //
    $aluPaintedKg = $aluKg * 1.01;
    $aluFireKg    = $aluPaintedKg * 0.07;

    //
    // Ek Maliyetler
    // Boya, fire ve işçilik gibi ek giderleri hesaplar.
    //
    $extras = [
        'paint'  => $aluPaintedKg * 200,
        // Fire cost calculated per kilogram of aluminum waste
        'waste'  => $aluFireKg * 200,
    ];
    $area = ($width * $height * $qty) / 1000000; // m²
    $extras['labor'] = $area * 40;

    //
    // Ekstra Maliyet Ayrıştırma
    // Boya ve fire dışındaki diğer ekstra giderleri toplar.
    //
    $paintCost       = $extras['paint'] ?? 0.0;
    $fireCost        = $extras['waste'] ?? 0.0;
    $otherExtras     = $extras;
    unset($otherExtras['paint'], $otherExtras['waste']);
    $otherExtrasCost = array_sum($otherExtras);

    //
    // Genel Toplam Hesabı
    // Alüminyum hariç tüm kalemlerin toplamını belirler.
    //
    $grandTotal = $paintCost
        + $fireCost
        + $aksesuarCost
        + $fitilCost
        + $glassCost
        + $otherExtrasCost;

    //
    // Genel Giderler
    // Genel giderleri ekleyerek nihai tutarı hesaplar.
    $generalExpense = $grandTotal * 0.01;
    $baseTotal      = $grandTotal + $generalExpense;

    //
    // Kâr Hesabı
    // Belirtilen kâr oranına göre kâr tutarını hesaplar ve son toplamı günceller.
    //
    $profitRate = max(0.0, (float) ($input['profit_rate'] ?? 0));
    $profit     = $baseTotal * $profitRate / 100;
    $finalTotal = $baseTotal + $profit;

    //
    // Toplamlar Dizisi
    // Hesaplanan tüm maliyetleri özetler.
    //
    $totals = [
        'alu_cost'        => $aluCost,
        'glass_cost'      => $glassCost,
        'aksesuar_cost'   => $aksesuarCost,
        'fitil_cost'      => $fitilCost,
        'extras'          => $extras,
        'general_expense' => $generalExpense,
        'profit'          => $profit,
        'grand_total'     => $finalTotal,
    ];

    //
    // Sonuç Dizisi
    // Satır detayları ve toplamları içeren nihai sonucu döndürür.
    //
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
    //
    // Sayfa Başlatma
    // Dosya doğrudan çalıştırıldığında başlık dosyasını dahil eder.
    //
    require __DIR__ . '/header.php';

    //
    // HTML Kaçış Fonksiyonu
    // Çıktıya güvenli metin yazmak için özel karakterleri dönüştürür.
    //
    function e(?string $v): string
    {
        return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
    }

    //
    // Birim Formatlama Fonksiyonu
    // Verilen birimi temel alarak sayısal değeri uygun biçimde gösterir.
    //
    function fmtUnit(float $value, string $unit): string
    {
        $unit = strtolower(trim($unit));
        $twoDecimals = ['metre', 'm', 'kilogram', 'kg', 'kg/m', 'metrekare', 'm²', 'm2'];
        $decimals = in_array($unit, $twoDecimals, true) ? 2 : 0;
        return number_format($value, $decimals, ',', '.');
    }

    //
    // Para Birimi Sembolü
    // Verilen para birimine karşılık gelen sembolü döndürür.
    //
    function currencySymbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'USD' => '$',
            'EUR' => '€',
            'TRY', 'TL' => '₺',
            default => $currency,
        };
    }


    //
    // PDO Ürün Sağlayıcı Sınıfı
    // Ürün bilgilerini veritabanından okumak için PDO kullanır.
    //
    class PdoProductProvider implements ProductProviderInterface
    {
        public function __construct(private PDO $pdo) {}

        //
        // Ürün Sorgusu
        // Verilen isimle eşleşen ürün kaydını veritabanından getirir.
        //
        public function getProduct(string $name): ?array
        {
            $stmt = $this->pdo->prepare('SELECT p.unit, p.unit_price, p.weight_per_meter, p.price_unit, c.name AS category FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE LOWER(p.name) = LOWER(:name)');
            $stmt->execute([':name' => $name]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        }
    }

    //
    // Giyotin Kimliği
    // URL'den gelen teklif kimliğini doğrular.
    //
    $id = filter_input(INPUT_GET, 'quote_id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo '<div class="container mt-4"><div class="alert alert-danger">Geçersiz giyotin.</div></div>';
        require __DIR__ . '/footer.php';
        exit;
    }

    //
    // Giyotin Verileri
    // Veritabanından ilgili ölçü ve ayarları çeker.
    //
    $stmt = $pdo->prepare('SELECT width, height, quantity, glass_type, motor_system, remote_quantity, profit_margin, general_offer_id FROM guillotinesystems WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo '<div class="container mt-4"><div class="alert alert-danger">Giyotin satırı bulunamadı.</div></div>';
        require __DIR__ . '/footer.php';
        exit;
    }

    //
    // Sağlayıcı ve Kur Oranları
    // Ürün sağlayıcı nesnesini oluşturur ve güncel kur bilgilerini alır.
    //
    $provider = new PdoProductProvider($pdo);

    $exchangeRates = fetchExchangeRates('TRY', ['USD', 'EUR']);

    //
    // Hesaplama Denemesi
    // Giyotin verileri ile toplam maliyet hesaplanır; hata olursa yakalanır.
    //
    try {
        $result = calculateGuillotineTotals([
            'width'         => $row['width'],
            'height'        => $row['height'],
            'quantity'      => $row['quantity'],
            'glass_type'    => $row['glass_type'] ?? '',
            'profit_rate'   => $row['profit_margin'] ?? 0,
            'currency'      => 'TRY',
            'exchange_rates' => $exchangeRates,
            'provider'      => $provider,
        ]);
    } catch (Throwable $e) {
        echo '<div class="container mt-4"><div class="alert alert-danger">Hesaplama hatası: ' . e($e->getMessage()) . '</div></div>';
        require __DIR__ . '/footer.php';
        exit;
    }

    echo '<div class="container mt-4">';

    if (!empty($exchangeRates)) {
        echo '<div class="row">';

        if (isset($exchangeRates['USD'])) {
            echo '<div class="col-md-6">';
            echo '<div class="alert alert-info text-center">';
            echo '1 USD = ' . e(number_format($exchangeRates['USD'], 2, ',', '.')) . ' ' . e(currencySymbol('TRY'));
            echo '</div>';
            echo '</div>';
        }

        if (isset($exchangeRates['EUR'])) {
            echo '<div class="col-md-6">';
            echo '<div class="alert alert-success text-center">';
            echo '1 EUR = ' . e(number_format($exchangeRates['EUR'], 2, ',', '.')) . ' ' . e(currencySymbol('TRY'));
            echo '</div>';
            echo '</div>';
        }

        echo '</div>'; // row
    }

    echo '</div>'; // container

    echo '<h3>Kalemler</h3>';

    //
    // Kategori Listesi
    // Hesaplanan satırları kategori bazında gruplamak için boş dizi oluşturur.
    //
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

    //
    // Toplam ve Cam Bilgisi
    // Hesaplamanın genel sonuçlarını ve cam ölçülerini alır.
    //
    $tot       = $result['totals'];
    $glassInfo = $result['glass'] ?? null;
    $currencySymbol = currencySymbol($result['currency']);

    //
    // Demonte Kalemleri
    // Motor ve kumanda için maliyet hesaplamaları yapılır.
    //
    $profitRate   = (float) ($row['profit_margin'] ?? 0);
    $demonteItems = [];

    $systemQty = (int) ($row['quantity'] ?? 0);
    $motorName = (string) ($row['motor_system'] ?? '');
    if ($motorName !== '') {
        $motorProduct = $provider->getProduct($motorName);
        if ($motorProduct) {
            $motorCurrency = strtoupper((string) ($motorProduct['price_unit'] ?? 'TRY'));
            $motorPrice    = (float) ($motorProduct['unit_price'] ?? 0);
            if ($motorCurrency !== $result['currency']) {
                $rate = $exchangeRates[$motorCurrency] ?? null;
                if ($rate !== null) {
                    $motorPrice *= $rate;
                }
            }
            $motorCost = $motorPrice * $systemQty;
            $demonteItems[] = [
                'name'       => $motorName,
                'unit_price' => $motorPrice,
                'qty'        => $systemQty,
                'cost'       => $motorCost,
            ];
        }
    }

    $remoteQty = (int) ($row['remote_quantity'] ?? 0);
    if ($remoteQty > 0) {
        $remoteProduct = $provider->getProduct('Kumanda');
        if ($remoteProduct) {
            $remoteCurrency = strtoupper((string) ($remoteProduct['price_unit'] ?? 'TRY'));
            $remotePrice    = (float) ($remoteProduct['unit_price'] ?? 0);
            if ($remoteCurrency !== $result['currency']) {
                $rate = $exchangeRates[$remoteCurrency] ?? null;
                if ($rate !== null) {
                    $remotePrice *= $rate;
                }
            }
            $remoteCost = $remotePrice * $remoteQty;
            $demonteItems[] = [
                'name'       => 'Kumanda',
                'unit_price' => $remotePrice,
                'qty'        => $remoteQty,
                'cost'       => $remoteCost,
            ];
        }
    }

    //
    // Demonte Toplamları
    // Motor ve kumanda kalemlerinin kâr ve toplam tutarlarını önceden hesaplar.
    //
    $demonteCostSum   = 0.0;
    $demonteProfitSum = 0.0;
    $demonteTotal     = 0.0;
    foreach ($demonteItems as &$item) {
        $item['profit'] = $item['cost'] * $profitRate / 100;
        $item['total']  = $item['cost'] + $item['profit'];
        $demonteCostSum   += $item['cost'];
        $demonteProfitSum += $item['profit'];
        $demonteTotal     += $item['total'];
    }
    unset($item);
    $salesTotal     = $tot['grand_total'] + $demonteTotal;
    $updatedProfit  = $tot['profit'] + $demonteProfitSum;

    //
    // Veritabanı Güncellemesi
    // Hesaplanan kâr ve satış tutarı ilgili giyotin satırına ve teklife kaydedilir.
    //
    $upd = $pdo->prepare('UPDATE guillotinesystems SET profit_amount = :p, total_amount = :t WHERE id = :id');
    $upd->execute([':p' => $updatedProfit, ':t' => $salesTotal, ':id' => $id]);

    $gSumStmt = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) FROM guillotinesystems WHERE general_offer_id = :gid');
    $gSumStmt->execute([':gid' => $row['general_offer_id']]);
    $gSum = (float) $gSumStmt->fetchColumn();
    $sSumStmt = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) FROM slidingsystems WHERE general_offer_id = :gid');
    $sSumStmt->execute([':gid' => $row['general_offer_id']]);
    $sSum = (float) $sSumStmt->fetchColumn();
    $offerUpd = $pdo->prepare('UPDATE generaloffers SET total_amount = :t WHERE id = :id');
    $offerUpd->execute([':t' => $gSum + $sSum, ':id' => $row['general_offer_id']]);

    //
    // Kategori Döngüsü
    // Her kategori için tablo oluşturarak satırları listeler.
    //
    $usdRate = $exchangeRates['USD'] ?? 0.0;
    foreach ($categories as $cat) {
        $isAlu = strcasecmp($cat['title'], 'Alüminyum') === 0;
        echo '<h5>' . e($cat['title']) . '</h5>';
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm table-hover mb-3">';
        echo '<thead><tr><th>Ad</th><th>Ölçü (mm)</th>';
        //
        // Alüminyum Kolonu
        // Kategori alüminyum ise adet sütunu başlığa eklenir.
        //
        if ($isAlu) {
            echo '<th>Adet</th>';
        }
        echo '<th>Miktar</th><th>Birim</th><th class="text-end">Tutar</th><th class="text-end">Birim Fiyat ($)</th><th class="text-end">Toplam Tutar ($)</th></tr></thead><tbody>';
        //
        // Kategori Toplayıcıları
        // Miktar, toplam tutar ve adet değerlerini sıfırlar.
        //
        $qtySum     = 0.0;
        $totalSum   = 0.0;
        $totalSumUsd = 0.0;
        $unit       = '';
        $pieceSum   = 0;
        //
        // Satır Döngüsü
        // Her kategori içindeki satırları tabloda gösterir.
        //
        foreach ($cat['lines'] as $line) {
            echo '<tr>';
            echo '<td>' . e($line['name']) . '</td>';
            echo '<td>' . e(number_format($line['measure'], 0, ',', '.')) . '</td>';
            //
            // Adet Bilgisi
            // Sadece alüminyum kalemlerde adet sütunu gösterilir.
            //
            if ($isAlu) {
                echo '<td>' . e(number_format((int) ($line['pieces'] ?? 0), 0, ',', '.')) . '</td>';
            }
            echo '<td>' . e(fmtUnit($line['quantity'], $line['unit'])) . '</td>';
            echo '<td>' . e($line['unit']) . '</td>';
            echo '<td class="text-end">' . e(number_format($line['total'], 2, ',', '.')) . ' ' . e(currencySymbol($line['currency'])) . '</td>';
            $unitPriceUsd = ($usdRate > 0 && $line['quantity'] > 0)
                ? ($line['total'] / $line['quantity']) / $usdRate
                : 0;
            $totalUsd = $usdRate > 0 ? $line['total'] / $usdRate : 0;
            echo '<td class="text-end">' . e(number_format($unitPriceUsd, 2, ',', '.')) . ' $</td>';
            echo '<td class="text-end">' . e(number_format($totalUsd, 2, ',', '.')) . ' $</td>';
            echo '</tr>';
            $qtySum     += $line['quantity'];
            $totalSum   += $line['total'];
            $totalSumUsd += $totalUsd;
            if ($isAlu) {
                $pieceSum += (int) ($line['pieces'] ?? 0);
            }
            //
            // Birim Tutarlılığı
            // Farklı satırlarda birim değişirse toplam satırda birim gösterilmez.
            //
            if ($unit === '') {
                $unit = $line['unit'];
            } elseif ($unit !== $line['unit']) {
                $unit = '';
            }
        }
        echo '<tr>';
        echo '<td colspan="2" class="text-end"><strong>Toplam</strong></td>';
        //
        // Adet Toplamı
        // Alüminyum kalemler için toplam adet değeri gösterilir.
        //
        if ($isAlu) {
            echo '<td>' . e(number_format($pieceSum, 0, ',', '.')) . '</td>';
        }
        echo '<td>' . e(fmtUnit($qtySum, $unit)) . '</td>';
        echo '<td>' . e($unit) . '</td>';
        echo '<td class="text-end">' . e(number_format($totalSum, 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
        echo '<td></td>';
        echo '<td class="text-end">' . e(number_format($totalSumUsd, 2, ',', '.')) . ' $</td>';
        echo '</tr>';
        echo '</tbody></table></div>';
    }

    //
    // Cam Bilgisi Kontrolü
    // Cam satırı varsa alanı ve maliyeti gösterir.
    //
    if ($glassInfo && $glassInfo['quantity'] > 0) {
        //
        // Tek Cam Alanı
        // Bir cam parçasının metrekare alanını hesaplar.
        //
        $singleArea = ($glassInfo['width'] * $glassInfo['height']) / 1000000;
        //
        // Toplam Cam Alanı
        // Tüm cam parçalarının toplam metrekare alanını bulur.
        //
        $totalArea  = $singleArea * $glassInfo['quantity'];
        echo '<h5>Cam</h5>';
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm table-hover mb-3">';
        echo '<thead><tr><th>Genişlik (mm)</th><th>Yükseklik (mm)</th><th>Adet</th><th>Birim m²</th><th>Toplam m²</th><th class="text-end">Tutar</th><th class="text-end">Birim Fiyat ($)</th><th class="text-end">Toplam Tutar ($)</th></tr></thead><tbody>';
        $unitPriceGlassUsd = ($usdRate > 0 && $totalArea > 0) ? ($tot['glass_cost'] / $totalArea) / $usdRate : 0;
        $totalGlassUsd = $usdRate > 0 ? $tot['glass_cost'] / $usdRate : 0;
        echo '<tr>';
        echo '<td>' . e(number_format($glassInfo['width'], 0, ',', '.')) . '</td>';
        echo '<td>' . e(number_format($glassInfo['height'], 0, ',', '.')) . '</td>';
        echo '<td>' . e(number_format($glassInfo['quantity'], 0, ',', '.')) . '</td>';
        echo '<td>' . e(number_format($singleArea, 2, ',', '.')) . '</td>';
        echo '<td>' . e(number_format($totalArea, 2, ',', '.')) . '</td>';
        echo '<td class="text-end">' . e(number_format($tot['glass_cost'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
        echo '<td class="text-end">' . e(number_format($unitPriceGlassUsd, 2, ',', '.')) . ' $</td>';
        echo '<td class="text-end">' . e(number_format($totalGlassUsd, 2, ',', '.')) . ' $</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<td colspan="3" class="text-end"><strong>Toplam</strong></td>';
        echo '<td></td>';
        echo '<td>' . e(number_format($totalArea, 2, ',', '.')) . '</td>';
        echo '<td class="text-end">' . e(number_format($tot['glass_cost'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
        echo '<td></td>';
        echo '<td class="text-end">' . e(number_format($totalGlassUsd, 2, ',', '.')) . ' $</td>';
        echo '</tr>';
        echo '</tbody></table></div>';
    }
    // Demonte Tablosu
    if (!empty($demonteItems)) {
        echo '<div class="mt-3">';
        echo '<h5>Demonte</h5>';
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm table-hover mb-3">';
        echo '<thead><tr><th>Ürün</th><th class="text-end">Birim Fiyat</th><th>Adet</th><th class="text-end">Maliyet</th><th class="text-end">Kâr (%)</th><th class="text-end">Demonte Kârı</th><th class="text-end">Demonte Tutarı</th></tr></thead><tbody>';
        foreach ($demonteItems as $item) {
            echo '<tr>';
            echo '<td>' . e($item['name']) . '</td>';
            echo '<td class="text-end">' . e(number_format($item['unit_price'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
            echo '<td>' . e(number_format($item['qty'], 0, ',', '.')) . '</td>';
            echo '<td class="text-end">' . e(number_format($item['cost'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
            echo '<td class="text-end">' . e(number_format($profitRate, 2, ',', '.')) . ' %</td>';
            echo '<td class="text-end">' . e(number_format($item['profit'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
            echo '<td class="text-end">' . e(number_format($item['total'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
            echo '</tr>';
        }
        echo '<tr class="table-success fw-bold">';
        echo '<td>Demonte Toplamı</td><td></td><td></td>';
        echo '<td class="text-end">' . e(number_format($demonteCostSum, 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
        echo '<td></td>';
        echo '<td class="text-end">' . e(number_format($demonteProfitSum, 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
        echo '<td class="text-end">' . e(number_format($demonteTotal, 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
        echo '</tr>';
        echo '</tbody></table></div></div>';
    }
    echo '<div class="mt-3">';
    echo '<table class="table table-bordered table-sm table-hover">';
    echo '<tbody>';

    echo '<tr><th>Alüminyum Boyalı ' . e(number_format($result['alu_painted_kg'], 2, ',', '.')) . ' kg</th><td>'
        . e(number_format($tot['extras']['paint'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td></tr>';
    echo '<tr><th>Alüminyum Fire ' . e(number_format($result['alu_fire_kg'], 2, ',', '.')) . ' kg</th><td>'
        . e(number_format($tot['extras']['waste'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td></tr>';
    echo '<tr><th>Aksesuar</th><td>' . e(number_format($tot['aksesuar_cost'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td></tr>';
    echo '<tr><th>Fitil</th><td>' . e(number_format($tot['fitil_cost'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td></tr>';
    echo '<tr><th>İmalat İşçiliği</th><td>' . e(number_format($tot['extras']['labor'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td></tr>';

    echo '<tr><th>Genel Gider</th><td>' . e(number_format($tot['general_expense'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td></tr>';

    echo '<tr><th>Kâr</th><td>' . e(number_format($tot['profit'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td></tr>';

    echo '<tr class="table-success fw-bold">';
    echo '<td>Genel Toplam</td><td>' . e(number_format($tot['grand_total'], 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
    echo '</tr>';
    if (!empty($demonteItems)) {
        echo '<tr><th>Demonte Toplamı</th><td>' . e(number_format($demonteTotal, 2, ',', '.')) . ' ' . e($currencySymbol) . '</td></tr>';
        echo '<tr class="table-primary fw-bold">';
        echo '<td>SATIŞ</td><td>' . e(number_format($salesTotal, 2, ',', '.')) . ' ' . e($currencySymbol) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';

    //
    // Sayfa Sonu
    // Alt bilgi şablonunu dahil eder ve sayfayı sonlandırır.
    //
    require __DIR__ . '/footer.php';
}
