<?php
require __DIR__ . '/header.php';

function e(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$input = array_merge($_GET, $_POST);

$system_width  = isset($input['system_width']) ? (float)$input['system_width'] : 0;
$system_height = isset($input['system_height']) ? (float)$input['system_height'] : 0;
$system_count  = isset($input['system_count']) ? (int)$input['system_count'] : 0;
$kenetli_baza_count   = isset($input['kenetli_baza_count']) ? (int)$input['kenetli_baza_count'] : 0;
$kupeste_baza_count   = isset($input['kupeste_baza_count']) ? (int)$input['kupeste_baza_count'] : 0;

$kg_keys = [
    'motor_kutusu' => 'Motor Kutusu',
    'motor_kapak' => 'Motor Kapak',
    'alt_kasa' => 'Alt Kasa',
    'tutamak' => 'Tutamak',
    'kenetli_baza' => 'Kenetli Baza',
    'kupeste_bazasi' => 'Küpeşte Bazası',
    'kupeste' => 'Küpeşte',
    'yatay_tek_cam_citasi' => 'Yatay Tek Cam Çıtası',
    'dikey_tek_cam_citasi' => 'Dikey Tek Cam Çıtası',
    'dikme' => 'Dikme',
    'orta_dikme' => 'Orta Dikme',
    'son_kapatma' => 'Son Kapatma',
    'kanat' => 'Kanat',
    'dikey_baza' => 'Dikey Baza',
    'zincir' => 'Zincir',
    'flatbelt' => 'Flatbelt Kayış',
    'motor_borusu' => 'Motor Borusu',
    'motor_kutu_contasi' => 'Motor Kutu Contası',
    'kanat_contasi' => 'Kanat Contası',
];

$kg_per_meter = [];
foreach ($kg_keys as $key => $label) {
    $kg_per_meter[$key] = isset($input['kg'][$key]) ? (float)$input['kg'][$key] : 0;
}

$results = [];
$errors = [];

if ($system_width > 0 && $system_height > 0 && $system_count > 0) {
    // 1. Motor Kutusu
    $motor_kutusu_measure = $system_width - 14;
    $motor_kutusu_quantity = $system_count;
    $motor_kutusu_kg = ($kg_per_meter['motor_kutusu'] * $motor_kutusu_measure * $motor_kutusu_quantity) / 1000;
    $motor_kutusu_gasket = $motor_kutusu_measure * $motor_kutusu_quantity; // mm
    $results[] = [
        'name' => 'Motor Kutusu',
        'measurement' => $motor_kutusu_measure,
        'quantity' => $motor_kutusu_quantity,
        'total_kg' => $motor_kutusu_kg,
        'extra' => 'Motor Kutu Contası: ' . $motor_kutusu_gasket . ' mm',
    ];

    // 2. Motor Kapak
    $motor_kapak_measure = $system_width - 15;
    $motor_kapak_quantity = $system_count;
    $motor_kapak_kg = ($kg_per_meter['motor_kapak'] * $motor_kapak_measure * $motor_kapak_quantity) / 1000;
    $results[] = [
        'name' => 'Motor Kapak',
        'measurement' => $motor_kapak_measure,
        'quantity' => $motor_kapak_quantity,
        'total_kg' => $motor_kapak_kg,
        'extra' => '',
    ];

    // 3. Alt Kasa
    $alt_kasa_measure = $system_width;
    $alt_kasa_quantity = $system_count;
    $alt_kasa_kg = ($kg_per_meter['alt_kasa'] * $alt_kasa_measure * $alt_kasa_quantity) / 1000;
    $alt_kasa_gasket = $alt_kasa_measure * $alt_kasa_quantity;
    $results[] = [
        'name' => 'Alt Kasa',
        'measurement' => $alt_kasa_measure,
        'quantity' => $alt_kasa_quantity,
        'total_kg' => $alt_kasa_kg,
        'extra' => 'Motor Kutu Contası: ' . $alt_kasa_gasket . ' mm',
    ];

    // 4. Tutamak
    $tutamak_measure = $system_width - 185;
    $tutamak_quantity = 6 * $system_count - ($kenetli_baza_count + $kupeste_baza_count);
    $tutamak_kg = ($kg_per_meter['tutamak'] * $tutamak_measure * $tutamak_quantity) / 1000;
    $tutamak_gasket = $tutamak_measure * $tutamak_quantity;
    $results[] = [
        'name' => 'Tutamak',
        'measurement' => $tutamak_measure,
        'quantity' => $tutamak_quantity,
        'total_kg' => $tutamak_kg,
        'extra' => 'Kıl Fitil Kısa: ' . $tutamak_gasket . ' mm',
    ];

    // 5. Kenetli Baza
    $kenetli_baza_measure = $system_width - 185;
    $kenetli_baza_quantity_calc = 3 * $system_count;
    $kenetli_baza_kg = ($kg_per_meter['kenetli_baza'] * $kenetli_baza_measure * $kenetli_baza_quantity_calc) / 1000;
    $kenetli_baza_gasket = $kenetli_baza_measure * $kenetli_baza_quantity_calc;
    $results[] = [
        'name' => 'Kenetli Baza',
        'measurement' => $kenetli_baza_measure,
        'quantity' => $kenetli_baza_quantity_calc,
        'total_kg' => $kenetli_baza_kg,
        'extra' => 'Kıl Fitil Kısa: ' . $kenetli_baza_gasket . ' mm',
    ];

    // 6. Küpeşte Bazası
    $kupeste_bazasi_measure = $system_width - 185;
    $kupeste_bazasi_quantity_calc = 2 * $system_count;
    $kupeste_bazasi_kg = ($kg_per_meter['kupeste_bazasi'] * $kupeste_bazasi_measure * $kupeste_bazasi_quantity_calc) / 1000;
    $results[] = [
        'name' => 'Küpeşte Bazası',
        'measurement' => $kupeste_bazasi_measure,
        'quantity' => $kupeste_bazasi_quantity_calc,
        'total_kg' => $kupeste_bazasi_kg,
        'extra' => '',
    ];

    // 7. Küpeşte
    $kupeste_measure = $system_width - 185;
    $kupeste_quantity = $system_count;
    $kupeste_kg = ($kg_per_meter['kupeste'] * $kupeste_measure * $kupeste_quantity) / 1000;
    $results[] = [
        'name' => 'Küpeşte',
        'measurement' => $kupeste_measure,
        'quantity' => $kupeste_quantity,
        'total_kg' => $kupeste_kg,
        'extra' => '',
    ];

    // Precomputed wing measurement
    $wing_measure = ($system_height - 290) / 3;

    // 8. Yatay Tek Cam Çıtası
    $yatay_cita_measure = $kenetli_baza_measure - 52;
    $yatay_cita_quantity = $tutamak_quantity + $kenetli_baza_quantity_calc + $kupeste_bazasi_quantity_calc;
    $yatay_cita_kg = ($kg_per_meter['yatay_tek_cam_citasi'] * $yatay_cita_measure * $yatay_cita_quantity) / 1000;
    $results[] = [
        'name' => 'Yatay Tek Cam Çıtası',
        'measurement' => $yatay_cita_measure,
        'quantity' => $yatay_cita_quantity,
        'total_kg' => $yatay_cita_kg,
        'extra' => '',
    ];

    // 9. Dikey Tek Cam Çıtası
    $dikey_baza_measure = $wing_measure; // used later as well
    $dikey_cita_measure = $dikey_baza_measure - 5;
    $dikey_cita_quantity = $yatay_cita_quantity;
    $dikey_cita_kg = ($kg_per_meter['dikey_tek_cam_citasi'] * $dikey_cita_measure * $dikey_cita_quantity) / 1000;
    $results[] = [
        'name' => 'Dikey Tek Cam Çıtası',
        'measurement' => $dikey_cita_measure,
        'quantity' => $dikey_cita_quantity,
        'total_kg' => $dikey_cita_kg,
        'extra' => '',
    ];

    // 10. Dikme
    $dikme_measure = $system_height - 166;
    $dikme_quantity = 2 * $system_count;
    $dikme_kg = ($kg_per_meter['dikme'] * $dikme_measure * $dikme_quantity) / 1000;
    $dikme_gasket = $dikme_measure * $dikme_quantity;
    $results[] = [
        'name' => 'Dikme',
        'measurement' => $dikme_measure,
        'quantity' => $dikme_quantity,
        'total_kg' => $dikme_kg,
        'extra' => 'Kıl Fitil Uzun: ' . $dikme_gasket . ' mm',
    ];

    // 11. Orta Dikme
    $orta_dikme_measure = $system_height - 166;
    $orta_dikme_quantity = 2 * $system_count;
    $orta_dikme_kg = ($kg_per_meter['orta_dikme'] * $orta_dikme_measure * $orta_dikme_quantity) / 1000;
    $orta_dikme_gasket = $orta_dikme_measure * $orta_dikme_quantity * 2;
    $results[] = [
        'name' => 'Orta Dikme',
        'measurement' => $orta_dikme_measure,
        'quantity' => $orta_dikme_quantity,
        'total_kg' => $orta_dikme_kg,
        'extra' => 'Kıl Fitil Uzun: ' . $orta_dikme_gasket . ' mm',
    ];

    // 12. Son Kapatma
    $son_kapatma_measure = $system_height - $wing_measure - 221;
    $son_kapatma_quantity = 2 * $system_count;
    $son_kapatma_kg = ($kg_per_meter['son_kapatma'] * $son_kapatma_measure * $son_kapatma_quantity) / 1000;
    $son_kapatma_gasket = $son_kapatma_measure * $son_kapatma_quantity;
    $results[] = [
        'name' => 'Son Kapatma',
        'measurement' => $son_kapatma_measure,
        'quantity' => $son_kapatma_quantity,
        'total_kg' => $son_kapatma_kg,
        'extra' => 'Kıl Fitil Uzun: ' . $son_kapatma_gasket . ' mm',
    ];

    // 13. Kanat
    $kanat_measure = $wing_measure;
    $kanat_quantity = 2 * $system_count;
    $kanat_kg = ($kg_per_meter['kanat'] * $kanat_measure * $kanat_quantity) / 1000;
    $kanat_kil_fitil = $kanat_measure * $kanat_quantity;
    $kanat_contasi_mm = $kanat_measure * $kanat_quantity * 2;
    $results[] = [
        'name' => 'Kanat',
        'measurement' => $kanat_measure,
        'quantity' => $kanat_quantity,
        'total_kg' => $kanat_kg,
        'extra' => 'Kıl Fitil Uzun: ' . $kanat_kil_fitil . ' mm, Kanat Contası: ' . $kanat_contasi_mm . ' mm',
    ];

    // 14. Dikey Baza
    $dikey_baza_quantity = $system_count * 4;
    $dikey_baza_kg = ($kg_per_meter['dikey_baza'] * $dikey_baza_measure * $dikey_baza_quantity) / 1000;
    $results[] = [
        'name' => 'Dikey Baza',
        'measurement' => $dikey_baza_measure,
        'quantity' => $dikey_baza_quantity,
        'total_kg' => $dikey_baza_kg,
        'extra' => '',
    ];

    // 15. Zincir
    $zincir_measure = $system_height - $wing_measure - 221 + 600;
    $zincir_quantity = 2 * $system_count;
    $zincir_kg = ($kg_per_meter['zincir'] * $zincir_measure * $zincir_quantity) / 1000;
    $results[] = [
        'name' => 'Zincir',
        'measurement' => $zincir_measure,
        'quantity' => $zincir_quantity,
        'total_kg' => $zincir_kg,
        'extra' => '',
    ];

    // 16. Flatbelt Kayış
    $flatbelt_measure = $zincir_measure;
    $flatbelt_quantity = 2 * $system_count;
    $flatbelt_kg = ($kg_per_meter['flatbelt'] * $flatbelt_measure * $flatbelt_quantity) / 1000;
    $results[] = [
        'name' => 'Flatbelt Kayış',
        'measurement' => $flatbelt_measure,
        'quantity' => $flatbelt_quantity,
        'total_kg' => $flatbelt_kg,
        'extra' => '',
    ];

    // 17. Motor Borusu
    $motor_borusu_measure = $system_width - 59;
    $motor_borusu_quantity = $system_count;
    $motor_borusu_kg = ($kg_per_meter['motor_borusu'] * $motor_borusu_measure * $motor_borusu_quantity) / 1000;
    $results[] = [
        'name' => 'Motor Borusu',
        'measurement' => $motor_borusu_measure,
        'quantity' => $motor_borusu_quantity,
        'total_kg' => $motor_borusu_kg,
        'extra' => '',
    ];

    // 18. Motor Kutu Contası
    $motor_kutu_contasi_measure = $motor_kutusu_gasket + $alt_kasa_gasket;
    $motor_kutu_contasi_kg = ($kg_per_meter['motor_kutu_contasi'] * $motor_kutu_contasi_measure) / 1000;
    $results[] = [
        'name' => 'Motor Kutu Contası',
        'measurement' => $motor_kutu_contasi_measure,
        'quantity' => 1,
        'total_kg' => $motor_kutu_contasi_kg,
        'extra' => '',
    ];

    // 19. Kanat Contası
    $kanat_contasi_measure = $kanat_contasi_mm;
    $kanat_contasi_kg = ($kg_per_meter['kanat_contasi'] * $kanat_contasi_measure) / 1000;
    $results[] = [
        'name' => 'Kanat Contası',
        'measurement' => $kanat_contasi_measure,
        'quantity' => 1,
        'total_kg' => $kanat_contasi_kg,
        'extra' => '',
    ];
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors[] = 'Lütfen tüm girişleri doldurun.';
}
?>
<div class="container py-4">
    <h1>Optimizasyon</h1>
    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= e($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="post" class="row g-3 mb-4">
        <div class="col-md-2">
            <label class="form-label">Sistem Genişliği (mm)</label>
            <input type="number" name="system_width" class="form-control" value="<?= e($system_width) ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Sistem Yüksekliği (mm)</label>
            <input type="number" name="system_height" class="form-control" value="<?= e($system_height) ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Sistem Adedi</label>
            <input type="number" name="system_count" class="form-control" value="<?= e($system_count) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Kenetli Baza Adedi</label>
            <input type="number" name="kenetli_baza_count" class="form-control" value="<?= e($kenetli_baza_count) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Küpeşte Bazası Adedi</label>
            <input type="number" name="kupeste_baza_count" class="form-control" value="<?= e($kupeste_baza_count) ?>" required>
        </div>

        <div class="col-12"><h2>Kg/m Değerleri</h2></div>
        <?php foreach ($kg_keys as $key => $label): ?>
            <div class="col-md-2">
                <label class="form-label"><?= e($label) ?></label>
                <input type="number" step="0.001" name="kg[<?= e($key) ?>]" class="form-control" value="<?= e($kg_per_meter[$key]) ?>">
            </div>
        <?php endforeach; ?>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">Hesapla</button>
        </div>
    </form>

    <?php if ($results): ?>
    <table class="table table-bordered table-striped table-sm">
        <thead>
            <tr>
                <th>Ürün</th>
                <th>Ölçü (mm)</th>
                <th>Adet</th>
                <th>Toplam Kg</th>
                <th>Ekstra</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><?= e($r['name']) ?></td>
                    <td><?= e((int)round($r['measurement'])) ?></td>
                    <td><?= e($r['quantity']) ?></td>
                    <td><?= e(number_format($r['total_kg'], 3, ',', '.')) ?></td>
                    <td><?= e($r['extra']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php';
