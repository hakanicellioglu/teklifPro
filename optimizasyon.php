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
 *   lines: array<int, array{category:string,name:string,measure:float,width:float,height:float,unit:string,quantity:float,pieces:int,total:float,currency:string,original_currency:string}>,
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
 *   system: array{width: float, height: float, quantity: int},
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
        ['name' => 'Tutamak',            'measure' => fn($w, $h, $q) => $w - 185,                        'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Kenetli Baza',       'measure' => fn($w, $h, $q) => $w - 185,                        'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Küpeşte Bazası',     'measure' => fn($w, $h, $q) => $w - 185,                        'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Küpeşte',           'measure' => fn($w, $h, $q) => $w - 185,                        'qty' => fn($w, $h, $q) => $q],
    ];

    //
    // Cam Çıtası Ekleri
    // Tek cam kullanıldığında yatay ve dikey çıtalar listeye eklenir.
    //
    if ($includeGlassStrips) {
        $rules[] = ['name' => 'Yatay Tek Cam Çıtası', 'measure' => fn($w, $h, $q) => ($w - 185) - 52,      'qty' => fn($w, $h, $q) => 6 * $q];
        $rules[] = ['name' => 'Dikey Tek Cam Çıtası', 'measure' => fn($w, $h, $q) => (($h - 290) / 3) - 6, 'qty' => fn($w, $h, $q) => 6 * $q];
    }

    //
    // Ek Parça Kuralları
    // Diğer tüm profil ve aksesuarların ölçü hesapları bu listeye eklenir.
    //
    $rules = array_merge($rules, [
        ['name' => 'Dikme',              'measure' => fn($w, $h, $q) => $h - 166,                        'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Orta Dikme',         'measure' => fn($w, $h, $q) => $h - 166,                        'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Son Kapatma',        'measure' => fn($w, $h, $q) => $h - (($h - 291) / 3) - 221,      'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Kanat',              'measure' => fn($w, $h, $q) => ($h - 291) / 3,                   'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Dikey Baza',         'measure' => fn($w, $h, $q) => ($h - 291) / 3,                   'qty' => fn($w, $h, $q) => 4 * $q],
        ['name' => 'Flatbelt Kayış',     'measure' => fn($w, $h, $q) => $h - (($h - 290) / 3) - 221 + 600, 'qty' => fn($w, $h, $q) => 2 * $q],
        ['name' => 'Motor Borusu',       'measure' => fn($w, $h, $q) => $w - 59,                          'qty' => fn($w, $h, $q) => $q],
        ['name' => 'Motor Kutu Contası', 'measure' => fn($w, $h, $q) => ($w - 14) * $q + $w * $q,         'qty' => fn($w, $h, $q) => 1],
        ['name' => 'Kanat Contası',      'measure' => fn($w, $h, $q) => ((($h - 291) / 3) * 2) * 2 * $q,  'qty' => fn($w, $h, $q) => 1],
        ['name' => 'Plastik Set',        'measure' => fn($w, $h, $q) => 1,                                'qty' => fn($w, $h, $q) => $q],
        ['name' => 'Zincir',       'measure'    => fn($w, $h, $q) => 1,                             'qty'        => fn($w, $h, $q) => $q],
        ['name' => 'Kıl Fitil', 'measure' => fn($w, $h, $q) => ((($w - 185) * 4) + (($h - 166) * 8) + ((($h - 291) / 3) * 2)/1000), 'qty' => fn($w, $h, $q) => 1],
    ]);

    //
    // Cam Ürünü Kuralı
    // Önceden hesaplanan cam ölçülerini kullanarak cam malzemesini listeye ekler ve
    // kart ızgarasında üst sırada yer alması için başa ekler.
    //
    $glassRule = [
        'name'    => 'Cam',
        'measure' => fn($w, $h, $q) => $glassWidth,
        'width'   => fn($w, $h, $q) => $glassWidth,
        'height'  => fn($w, $h, $q) => $glassHeight,
        'qty'     => fn($w, $h, $q) => $glassQty,
    ];
    array_unshift($rules, $glassRule);

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
        $productCode   = (string) ($product['product_code'] ?? '');
        $imageUrl      = $product['image_url'] ?? null;

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
            'product_code'     => $productCode,
            'image_url'        => $imageUrl,
            'measure'          => $measure,
            'width'            => $ruleWidth,
            'height'           => $ruleHeight,
            'weight_per_meter' => $wpm,
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
        'system'         => [
            'width'    => $width,
            'height'   => $height,
            'quantity' => $qty,
        ],
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
            $stmt = $this->pdo->prepare('SELECT p.product_code, p.image_url, p.unit, p.unit_price, p.weight_per_meter, p.price_unit, c.name AS category FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE LOWER(p.name) = LOWER(:name)');
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
        echo '<div class="container"><div class="alert alert-danger">Geçersiz giyotin.</div></div>';
        require __DIR__ . '/footer.php';
        exit;
    }

    //
    // Giyotin Verileri
    // Veritabanından ilgili ölçü ve ayarları çeker.
    //
    $stmt = $pdo->prepare('SELECT width, height, quantity, glass_type, glass_color, motor_system, remote_quantity, ral_code, profit_margin, general_offer_id FROM guillotinesystems WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo '<div class="container"><div class="alert alert-danger">Giyotin satırı bulunamadı.</div></div>';
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
            'currency'      => 'TRY',
            'exchange_rates' => $exchangeRates,
            'provider'      => $provider,
        ]);
    } catch (Throwable $e) {
        echo '<div class="container"><div class="alert alert-danger">Hesaplama hatası: ' . e($e->getMessage()) . '</div></div>';
        require __DIR__ . '/footer.php';
        exit;
    }

    // Firma bilgileri ve hesaplanan satırlar
    $cStmt = $pdo->prepare('SELECT name, logo FROM company WHERE user_id = :uid LIMIT 1');
    $cStmt->execute([':uid' => $_SESSION['user_id']]);
    $company = $cStmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => '', 'logo' => null];

    $custStmt = $pdo->prepare('SELECT c.first_name, c.last_name, c.company_name, c.email, c.phone, c.address FROM generaloffers g LEFT JOIN customers c ON g.customer_id = c.id WHERE g.id = :id LIMIT 1');
    $custStmt->execute([':id' => $row['general_offer_id']]);
    $customer = $custStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $lines = $result['lines'];
    $tot       = $result['totals'];
    // Yeni kart ızgarası çıktısı
    echo '<style>
@page { size: A4; }
.product-grid { display: grid; gap: 0.125rem; grid-template-columns: repeat(auto-fill,minmax(140px,1fr)); }
@media (min-width:768px){ .product-grid { grid-template-columns: repeat(2,1fr); } }
@media (min-width:992px){ .product-grid { grid-template-columns: repeat(3,1fr); } }
@media print {
  .product-grid { grid-template-columns: repeat(3,1fr); }
}
.product-card { border:1px solid #000; page-break-inside: avoid; break-inside: avoid; }
.product-card table { width:100%; font-size:0.5rem; text-align:center; border-collapse:collapse; }
.product-card th, .product-card td { padding:0.1rem; }
  .product-card th { font-weight:600; }
  .product-img { width:100%; height:60px; object-fit:contain; border:1px solid #000; display:block; background-color:#fff; }
  .info-table { font-size:0.6rem; }
.info-table th, .info-table td { padding:0.1rem; }
</style>';

    echo '<div class="container d-print-none text-end">';
    echo '    <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm">🖨️ Yazdır</button>';
    echo '</div>';

    echo '<div class="text-center">';
    if (!empty($company['logo']) && file_exists(__DIR__ . '/assets/' . $company['logo'])) {
        echo '<img src="assets/' . e($company['logo']) . '" alt="' . e($company['name']) . ' Logo" style="max-height:60px;">';
    }
    echo '<h2 class="h5 fw-bold">GİYOTİN SİSTEMİ</h2>';
    echo '</div>';
    echo '<div class="row">';

    // --- [1] SİSTEM BİLGİSİ ---
    $sysWidth  = number_format($result['system']['width'], 0, ',', '.');
    $sysHeight = number_format($result['system']['height'], 0, ',', '.');
    $sysQtyVal = number_format((int) ($result['system']['quantity'] ?? 0), 0, ',', '.');
    $remoteQty = (int) ($row['remote_quantity'] ?? 0);
    $motorName = trim((string) ($row['motor_system'] ?? ''));
    $ralCode   = trim((string) ($row['ral_code'] ?? ''));

    echo '<div class="col">';
    echo '<table class="table table-bordered table-sm w-auto info-table" style="background-color:#fff;"><tbody>';
    echo '<tr><th>Sistem Genişliği</th><td>' . e($sysWidth) . '</td></tr>';
    echo '<tr><th>Sistem Yüksekliği</th><td>' . e($sysHeight) . '</td></tr>';
    echo '<tr><th>Sistem Adedi</th><td>' . e($sysQtyVal) . '</td></tr>';
    if ($remoteQty > 0) {
        echo '<tr><th>Kumanda Adedi</th><td>' . e((string) $remoteQty) . '</td></tr>';
    }
    if ($motorName !== '') {
        echo '<tr><th>Motor Sistemi</th><td>' . e($motorName) . '</td></tr>';
    }
    if ($ralCode !== '') {
        echo '<tr><th>RAL Kodu</th><td>' . e($ralCode) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '</div>';

    // --- [2] CAM BİLGİSİ ---
    $glassWidth  = number_format($result['glass']['width'], 0, ',', '.');
    $glassHeight = number_format($result['glass']['height'], 0, ',', '.');
    $glassQtyVal = number_format((int) ($result['glass']['quantity'] ?? 0), 0, ',', '.');
    $glassType   = trim((string) ($row['glass_type'] ?? ''));
    $glassColor  = trim((string) ($row['glass_color'] ?? ''));

    echo '<div class="col">';
    echo '<table class="table table-bordered table-sm w-auto info-table" style="background-color:#fff;">';
    echo '<thead><tr>';
    echo '<th>Cam Genişliği</th>';
    echo '<th>Cam Yüksekliği</th>';
    echo '<th>Cam Adedi</th>';
    echo '<th>Cam Kombinasyonu</th>';
    echo '<th>Cam Rengi</th>';
    echo '</tr></thead>';
    echo '<tbody><tr>';
    echo '<td>' . e($glassWidth) . '</td>';
    echo '<td>' . e($glassHeight) . '</td>';
    echo '<td>' . e($glassQtyVal) . '</td>';
    echo '<td>' . e($glassType) . '</td>';
    echo '<td>' . e($glassColor) . '</td>';
    echo '</tr></tbody></table>';
    echo '</div>';

    // --- [3] BİRLEŞİK: MÜŞTERİ + (SİSTEM, ALÜMİNYUM, CAM) ALANLARI ---
    $systemArea = ($result['system']['width'] * $result['system']['height'] * $result['system']['quantity']) / 1000000;
    $aluminumTotal = 0.0;
    foreach ($lines as $line) {
        if (strtolower($line['category']) === 'alüminyum') {
            $aluminumTotal += ($line['measure'] * ($line['pieces'] ?? 0)) / 1000;
        }
    }
    $glassArea = ($result['glass']['width'] * $result['glass']['height'] * $result['glass']['quantity']) / 1000000;

    echo '<div class="col">';
    echo '<div class="d-flex gap-3 align-items-start">';

    echo '<table class="table table-bordered table-sm w-auto info-table" style="background-color:#fff;">';
    echo '<thead><tr>';
    echo '<th>Müşteri Bilgileri</th>';
    echo '<th></th>';
    echo '</tr></thead>';
    echo '<tbody><tr><td valign="top">';

    // --- Sol Sütun: Müşteri Bilgileri ---
    if ($customer) {
        $fullName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
        if ($fullName !== '') {
            echo '<div><strong>Müşteri:</strong> ' . e($fullName) . '</div>';
        }
        if (!empty($customer['company_name'])) {
            echo '<div><strong>Firma:</strong> ' . e($customer['company_name']) . '</div>';
        }
        if (!empty($customer['phone'])) {
            echo '<div><strong>Telefon:</strong> ' . e($customer['phone']) . '</div>';
        }
        if (!empty($customer['email'])) {
            echo '<div><strong>E-posta:</strong> ' . e($customer['email']) . '</div>';
        }
        if (!empty($customer['address'])) {
            echo '<div><strong>Adres:</strong><br>' . nl2br(e($customer['address'])) . '</div>';
        }
    }

    echo '</td><td valign="top">';

    // --- Sağ Sütun: Sistem / Alüminyum / Cam ---
    echo '<div>';
    echo '  <div><strong>Sistem</strong></div>';
    echo '  <div>' . e(number_format($systemArea, 2, ',', '.')) . '</div>';
    echo '</div>';

    echo '<div>';
    echo '  <div><strong>Alüminyum</strong></div>';
    echo '  <div>' . e(number_format($aluminumTotal, 2, ',', '.')) . '</div>';
    echo '</div>';

    echo '<div>';
    echo '  <div><strong>Cam</strong></div>';
    echo '  <div>' . e(number_format($glassArea, 2, ',', '.')) . '</div>';
    echo '</div>';


    echo '</td></tr></tbody></table>';


    echo '</div>'; // d-flex
    echo '</div>'; // col

    echo '</div>'; // row


    echo '<div class="product-grid">';
    foreach ($lines as $line) {
        if (strtolower($line['category']) === 'cam') {
            continue;
        }
        $img = $line['image_url'] ?? '';
        if (!$img || !is_file(__DIR__ . '/' . $img)) {
            $img = 'assets/img/placeholder-product.png';
        }
        echo '<div class="product-card">';
        $qtyVal = number_format((int) ($line['pieces'] ?? 0), 0, ',', '.');
        $measureVal = number_format($line['measure'], 0, ',', '.');
        echo '<table class="table table-bordered table-sm">';
        echo '<thead><tr><th>İsim</th><th>Kod</th><th>Ölçü</th><th>Adet</th></tr></thead>';
        echo '<tbody><tr><td>' . e($line['name']) . '</td><td>' . e($line['product_code'] ?? '') . '</td><td>' . e($measureVal) . '</td><td>' . e($qtyVal) . '</td></tr></tbody>';
        echo '</table>';
        echo '<img src="' . e($img) . '" alt="' . e($line['name']) . '" loading="lazy" class="product-img">';
        echo '</div>';
    }
    echo '</div>';

    require __DIR__ . '/footer.php';
    return;
}
