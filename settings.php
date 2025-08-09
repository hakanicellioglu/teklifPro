<?php
require __DIR__ . '/header.php';

$colorErrors = [];
$colorSuccess = '';
$passErrors = [];
$passSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_color') {
        $coverColor = trim($_POST['cover_color'] ?? '');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $coverColor)) {
            $colorErrors[] = 'Invalid color value.';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE users SET cover_color = :color WHERE id = :id');
                $stmt->execute(['color' => $coverColor, 'id' => $_SESSION['user_id']]);
                $colorSuccess = 'Cover color updated.';
            } catch (PDOException $e) {
                $colorErrors[] = 'Could not save cover color.';
            }
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $passErrors[] = 'All fields are required.';
        } elseif ($new !== $confirm) {
            $passErrors[] = 'New passwords do not match.';
        } elseif (strlen($new) < 6) {
            $passErrors[] = 'New password must be at least 6 characters.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id');
                $stmt->execute(['id' => $_SESSION['user_id']]);
                $hash = $stmt->fetchColumn();

                if (!$hash || !password_verify($current, $hash)) {
                    $passErrors[] = 'Current password is incorrect.';
                } else {
                    $newHash = password_hash($new, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
                    $stmt->execute(['password' => $newHash, 'id' => $_SESSION['user_id']]);
                    $passSuccess = 'Password updated successfully.';
                }
            } catch (PDOException $e) {
                $passErrors[] = 'Could not update password.';
            }
        }
    }
}

try {
    $stmt = $pdo->prepare('SELECT first_name, last_name, username, created_at, status, cover_color FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $user = [];
}

$coverColor = $user['cover_color'] ?? '#ffffff';
?>
<div class="container py-4">
    <div class="card mb-4">
        <div class="card-header">Profile Header</div>
        <div class="card-body">
            <?php if ($colorErrors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($colorErrors as $error): ?>
                        <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($colorSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($colorSuccess, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="update_color">
                <div class="mb-3">
                    <label for="cover_color" class="form-label">Cover Color</label>
                    <input type="color" class="form-control form-control-color" id="cover_color" name="cover_color" value="<?= htmlspecialchars($coverColor, ENT_QUOTES, 'UTF-8') ?>" title="Choose your color">
                </div>
                <button type="submit" class="btn btn-primary">Save Color</button>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Profile Information</div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">First Name</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-3">Last Name</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-3">Username</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-3">Joined</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($user['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($user['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd>
            </dl>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Security</div>
        <div class="card-body">
            <?php if ($passErrors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($passErrors as $error): ?>
                        <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($passSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($passSuccess, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="change_password">
                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary">Change Password</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>