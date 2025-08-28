<?php
require __DIR__ . '/header.php';

$toasts = [];

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['profile_update'])) {
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name'] ?? '');
        $color = $_POST['cover_color'] ?? '#343a40';

        try {
            $pdo->beginTransaction();

            // Update user names
            $stmt = $pdo->prepare('UPDATE users SET first_name = :first, last_name = :last WHERE id = :id');
            $stmt->execute([
                'first' => $first,
                'last'  => $last,
                'id'    => $_SESSION['user_id']
            ]);

            // Upsert theme color
            $tStmt = $pdo->prepare('INSERT INTO themes (user_id, primary_color) VALUES (:uid, :color) ON DUPLICATE KEY UPDATE primary_color = VALUES(primary_color)');
            $tStmt->execute([
                'uid'   => $_SESSION['user_id'],
                'color' => $color
            ]);

            $pdo->commit();
            $toasts[] = ['type' => 'success', 'message' => 'Profil güncellendi.'];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $toasts[] = ['type' => 'danger', 'message' => 'Profil güncellenemedi.'];
        }
    }

    // Handle Password Change
    if (isset($_POST['password_change'])) {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $repeat  = $_POST['new_password_confirm'] ?? '';

        if (strlen($new) < 8) {
            $toasts[] = ['type' => 'danger', 'message' => 'Yeni parola en az 8 karakter olmalıdır.'];
        } elseif ($new !== $repeat) {
            $toasts[] = ['type' => 'danger', 'message' => 'Yeni parola eşleşmiyor.'];
        } else {
            try {
                $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id');
                $stmt->execute(['id' => $_SESSION['user_id']]);
                $hash = $stmt->fetchColumn();

                if (!$hash || !password_verify($current, $hash)) {
                    $toasts[] = ['type' => 'danger', 'message' => 'Mevcut parola doğrulanamadı.'];
                } else {
                    $newHash = password_hash($new, PASSWORD_ARGON2ID);
                    $uStmt = $pdo->prepare('UPDATE users SET password = :pwd WHERE id = :id');
                    $uStmt->execute([
                        'pwd' => $newHash,
                        'id'  => $_SESSION['user_id']
                    ]);
                    $toasts[] = ['type' => 'success', 'message' => 'Parola güncellendi.'];
                }
            } catch (Exception $e) {
                $toasts[] = ['type' => 'danger', 'message' => 'Parola güncellenemedi.'];
            }
        }
    }
}

// Fetch user info
try {
    $uStmt = $pdo->prepare('SELECT u.first_name, u.last_name, u.username, u.email, u.created_at, u.status, r.name AS role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = :id');
    $uStmt->execute(['id' => $_SESSION['user_id']]);
    $user = $uStmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $user = [];
}

// Fetch theme color
try {
    $tStmt = $pdo->prepare('SELECT primary_color FROM themes WHERE user_id = :id');
    $tStmt->execute(['id' => $_SESSION['user_id']]);
    $coverColor = $tStmt->fetchColumn() ?: '#343a40';
} catch (Exception $e) {
    $coverColor = '#343a40';
}

$initial  = strtoupper(substr($user['first_name'] ?? ($user['username'] ?? ''), 0, 1));
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

// Fetch stats
try {
    $totalOffers = (int)$pdo->query('SELECT COUNT(*) FROM generaloffers')->fetchColumn();
} catch (Exception $e) {
    $totalOffers = 0;
}
try {
    $acceptedOffers = (int)$pdo->query("SELECT COUNT(*) FROM generaloffers WHERE status = 'accepted'")->fetchColumn();
} catch (Exception $e) {
    $acceptedOffers = 0;
}
try {
    $customerCount = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
} catch (Exception $e) {
    $customerCount = 0;
}
try {
    $productCount  = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
} catch (Exception $e) {
    $productCount = 0;
}
try {
    $revenue       = (float)$pdo->query('SELECT COALESCE(SUM(total_amount),0) FROM generaloffers')->fetchColumn();
} catch (Exception $e) {
    $revenue = 0;
}

$acceptRate = $totalOffers > 0 ? round(($acceptedOffers / $totalOffers) * 100) : 0;

// --- Head injection for icons (if not already in header.php)
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
    .cover-card {
        background:
            linear-gradient(135deg, rgba(0, 0, 0, .35), rgba(0, 0, 0, .15)),
            var(--cover-color, #343a40);
        border: 0;
    }

    .avatar-xl {
        width: 76px;
        height: 76px;
        font-size: 34px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, .15);
    }

    .nav-underline .nav-link {
        padding-bottom: .8rem;
    }

    .stat-card .icon {
        font-size: 22px;
        opacity: .75;
    }

    .copy-btn {
        white-space: nowrap;
    }

    .form-hint {
        font-size: .85rem;
        color: var(--bs-secondary-color);
    }

    .strength {
        height: .5rem;
        border-radius: .5rem;
        background: var(--bs-secondary-bg);
        overflow: hidden;
    }

    .strength>div {
        height: 100%;
        transition: width .25s ease;
    }
</style>

<div class="container py-4">
    <div class="card cover-card text-white rounded-4 mb-4" id="coverCard"
        style="--cover-color: <?= htmlspecialchars($coverColor, ENT_QUOTES, 'UTF-8'); ?>;">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-white text-dark d-flex align-items-center justify-content-center avatar-xl fw-bold">
                    <?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2">
                        <div class="fw-bold fs-4"><?= htmlspecialchars($fullName ?: ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if (!empty($user['role_name'])): ?>
                            <span class="badge bg-light text-dark"><?= htmlspecialchars($user['role_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                        <?php if (!empty($user['email'])): ?>
                            <span class="small"><i class="bi bi-envelope"></i>
                                <span id="userEmail"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></span></span>
                            <button type="button" class="btn btn-light btn-sm copy-btn"
                                onclick="navigator.clipboard.writeText(document.getElementById('userEmail').textContent)">
                                Kopyala
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($user['created_at'])): ?>
                        <span class="small"><i class="bi bi-calendar-event"></i>
                            Üyelik: <?= htmlspecialchars(date('d.m.Y', strtotime($user['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($user['status'])): ?>
                        <span class="small"><i class="bi bi-shield-check"></i>
                            Durum: <?= htmlspecialchars($user['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="d-none d-md-flex gap-2">
                    <button type="button" class="btn btn-light btn-sm" onclick="openTab('profile-edit-tab');">
                        <i class="bi bi-person-gear me-1"></i>Profili Düzenle
                    </button>
                    <button type="button" class="btn btn-outline-light btn-sm" onclick="openTab('security-tab');">
                        <i class="bi bi-shield-lock me-1"></i>Güvenlik
                    </button>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs nav-underline" id="settingsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">Genel Bakış</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="profile-edit-tab" data-bs-toggle="tab" data-bs-target="#profile-edit" type="button" role="tab">Profili Düzenle</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">Güvenlik</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">Aktiviteler</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="company-tab" data-bs-toggle="tab" data-bs-target="#company" type="button" role="tab">Şirket</button>
        </li>
    </ul>

    <div class="tab-content pt-3">
        <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
            <div class="row g-3">
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card rounded-3 shadow-sm text-center stat-card">
                        <div class="card-body">
                            <div class="icon"><i class="bi bi-file-earmark-text"></i></div>
                            <div class="fw-semibold">Toplam Teklif</div>
                            <div class="fs-4"><?= (int)$totalOffers; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card rounded-3 shadow-sm text-center stat-card">
                        <div class="card-body">
                            <div class="icon"><i class="bi bi-hand-thumbs-up"></i></div>
                            <div class="fw-semibold">Kabul Edilen</div>
                            <div class="fs-4"><?= (int)$acceptedOffers; ?></div>
                            <div class="strength mt-2" aria-hidden="true">
                                <div style="width: <?= $acceptRate; ?>%; background: var(--bs-success);"></div>
                            </div>
                            <div class="small text-secondary-emphasis mt-1"><?= $acceptRate; ?>% onay oranı</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card rounded-3 shadow-sm text-center stat-card">
                        <div class="card-body">
                            <div class="icon"><i class="bi bi-people"></i></div>
                            <div class="fw-semibold">Müşteri</div>
                            <div class="fs-4"><?= (int)$customerCount; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card rounded-3 shadow-sm text-center stat-card">
                        <div class="card-body">
                            <div class="icon"><i class="bi bi-box-seam"></i></div>
                            <div class="fw-semibold">Ürün/Hizmet</div>
                            <div class="fs-4"><?= (int)$productCount; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-8 col-xl-4">
                    <div class="card rounded-3 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold">Toplam Ciro</div>
                                    <div class="fs-4"><?= number_format($revenue, 2, ',', '.'); ?></div>
                                </div>
                                <i class="bi bi-cash-stack fs-2 opacity-75"></i>
                            </div>
                            <div class="text-secondary mt-2 small">Toplam “generaloffers.total_amount” üzerinden hesaplanır.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="profile-edit" role="tabpanel" aria-labelledby="profile-edit-tab">
            <form method="post" class="row g-3">
                <input type="hidden" name="profile_update" value="1">
                <div class="col-md-6">
                    <label class="form-label">Ad</label>
                    <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Soyad</label>
                    <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Kullanıcı Adı</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hesap Durumu</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Kapak Rengi</label>
                    <input type="color" name="cover_color" id="coverColor" class="form-control form-control-color"
                        value="<?= htmlspecialchars($coverColor, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-hint">Renk seçildiğinde üstteki kapakta canlı önizleme yapılır.</div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Kaydet</button>
                </div>
            </form>
        </div>

        <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
            <form method="post" class="row g-3" autocomplete="off" id="pwdForm">
                <input type="hidden" name="password_change" value="1">
                <div class="col-md-4">
                    <label class="form-label">Mevcut Parola</label>
                    <div class="input-group">
                        <input type="password" name="current_password" class="form-control" required>
                        <button class="btn btn-outline-secondary toggle-pass" type="button"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Yeni Parola</label>
                    <div class="input-group">
                        <input type="password" name="new_password" id="newPwd" class="form-control" minlength="8" required>
                        <button class="btn btn-outline-secondary toggle-pass" type="button"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="strength mt-2" aria-hidden="true">
                        <div id="pwdBar" style="width:0%; background: var(--bs-danger);"></div>
                    </div>
                    <div id="pwdHint" class="form-hint">En az 8 karakter, harf + rakam önerilir.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Yeni Parola (Tekrar)</label>
                    <div class="input-group">
                        <input type="password" name="new_password_confirm" id="newPwd2" class="form-control" required>
                        <button class="btn btn-outline-secondary toggle-pass" type="button"><i class="bi bi-eye"></i></button>
                    </div>
                    <div id="matchHint" class="form-hint"></div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-shield-lock me-1"></i>Parolayı Güncelle</button>
                </div>
            </form>
        </div>

        <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
            <div class="card rounded-3 shadow-sm">
                <div class="card-body">
                    <p class="text-muted mb-3">Aktivite bulunmuyor.</p>
                    <ul class="list-unstyled small text-secondary mb-0">
                        <li><i class="bi bi-dot"></i> Son giriş, tarayıcı ve IP gibi olayları burada listeleyebilirsiniz.</li>
                        <li><i class="bi bi-dot"></i> Güvenlik uyarıları ve parola değişiklikleri kronolojik görünebilir.</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="company" role="tabpanel" aria-labelledby="company-tab">
            <?php
            define('SETTINGS_COMPANY_EMBED', true);
            require __DIR__ . '/company.php';
            ?>
        </div>
    </div>
</div>

<?php if (!empty($toasts)): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1100;">
        <?php foreach ($toasts as $t): ?>
            <div class="toast align-items-center text-bg-<?= htmlspecialchars($t['type'], ENT_QUOTES, 'UTF-8'); ?> border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3500">
                <div class="d-flex">
                    <div class="toast-body"><?= htmlspecialchars($t['message'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Kapat"></button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.bootstrap?.Toast) {
                document.querySelectorAll('.toast').forEach(el => new bootstrap.Toast(el).show());
            }
        });
    </script>
<?php endif; ?>

<script>
function openTab(buttonId) {
    const trigger = document.getElementById(buttonId);
    if (!trigger) return;
    if (window.bootstrap?.Tab) {
        new bootstrap.Tab(trigger).show();
    } else {
        document.querySelectorAll('#settingsTabs [data-bs-toggle="tab"]').forEach(btn => {
            const pane = document.querySelector(btn.getAttribute('data-bs-target'));
            const active = btn === trigger;
            btn.classList.toggle('active', active);
            pane?.classList.toggle('show', active);
            pane?.classList.toggle('active', active);
        });
    }
    localStorage.setItem('settingsActiveTab', buttonId);
}

document.addEventListener('DOMContentLoaded', () => {
    const stored = localStorage.getItem('settingsActiveTab');
    if (stored) openTab(stored);

    document.querySelectorAll('#settingsTabs [data-bs-toggle="tab"]').forEach(btn => {
        if (window.bootstrap?.Tab) {
            btn.addEventListener('shown.bs.tab', () => {
                localStorage.setItem('settingsActiveTab', btn.id);
            });
        } else {
            btn.addEventListener('click', () => openTab(btn.id));
        }
    });

    document.getElementById('coverColor')?.addEventListener('input', (e) => {
        document.getElementById('coverCard')?.style.setProperty('--cover-color', e.target.value);
    });

    document.querySelectorAll('.toggle-pass').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.parentElement.querySelector('input');
            if (!input) return;
            const isPwd = input.type === 'password';
            input.type = isPwd ? 'text' : 'password';
            btn.innerHTML = isPwd ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        });
    });

    const newPwd = document.getElementById('newPwd');
    const newPwd2 = document.getElementById('newPwd2');
    const bar = document.getElementById('pwdBar');
    const hint = document.getElementById('pwdHint');
    const matchHint = document.getElementById('matchHint');

    function scorePwd(val) {
        let s = 0;
        if (val.length >= 8) s += 1;
        if (/[A-ZÇĞİÖŞÜ]/.test(val)) s += 1;
        if (/[a-zçğıöşü]/.test(val)) s += 1;
        if (/\d/.test(val)) s += 1;
        if (/[^A-Za-z0-9çğıöşüÇĞİÖŞÜ]/.test(val)) s += 1;
        return s;
    }

    function updateStrength() {
        if (!bar || !hint || !matchHint) return;
        const v = newPwd?.value ?? '';
        const sc = scorePwd(v);
        const pct = (sc / 5) * 100;
        bar.style.width = pct + '%';
        let color = 'var(--bs-danger)';
        let txt = 'Zayıf';
        if (sc >= 2) {
            color = 'var(--bs-warning)';
            txt = 'Orta';
        }
        if (sc >= 4) {
            color = 'var(--bs-success)';
            txt = 'İyi';
        }
        bar.style.background = color;
        hint.textContent = 'Güç: ' + txt;
        if (newPwd2?.value) {
            const match = newPwd.value === newPwd2.value;
            matchHint.textContent = match ? 'Parolalar eşleşiyor.' : 'Parolalar eşleşmiyor.';
            matchHint.className = 'form-hint ' + (match ? 'text-success' : 'text-danger');
        } else {
            matchHint.textContent = '';
            matchHint.className = 'form-hint';
        }
    }
    newPwd?.addEventListener('input', updateStrength);
    newPwd2?.addEventListener('input', updateStrength);

    document.getElementById('pwdForm')?.addEventListener('submit', (e) => {
        if (!newPwd || !newPwd2) return;
        if (newPwd.value.length < 8) {
            e.preventDefault();
            alert('Yeni parola en az 8 karakter olmalıdır.');
        } else if (newPwd.value !== newPwd2.value) {
            e.preventDefault();
            alert('Yeni parola eşleşmiyor.');
        }
    });
});
</script>

<?php require __DIR__ . '/footer.php'; ?>
