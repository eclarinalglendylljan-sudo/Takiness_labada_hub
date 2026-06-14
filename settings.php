<?php
/**
 * settings.php
 * Takines Labada Hub — Settings
 * Owner only — shop information and account password
 */
require_once __DIR__ . '/config.php';
require_role('owner');

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = $_POST['form'] ?? '';

    if ($form === 'shop') {
        $shopName    = trim($_POST['shop_name'] ?? '');
        $shopAddress = trim($_POST['shop_address'] ?? '');
        $shopContact = trim($_POST['shop_contact'] ?? '');

        if ($shopName === '') {
            flash('error', 'Shop name cannot be empty.');
            redirect('settings.php');
        }

        $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE setting_value = ?');
        $stmt->execute(['shop_name', $shopName, $shopName]);
        $stmt->execute(['shop_address', $shopAddress, $shopAddress]);
        $stmt->execute(['shop_contact', $shopContact, $shopContact]);

        flash('success', 'Shop information updated.');
        redirect('settings.php');
    }

    if ($form === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$user->id]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row->password)) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            flash('error', 'New password and confirmation do not match.');
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$hash, $user->id]);
            flash('success', 'Password updated successfully.');
        }
        redirect('settings.php');
    }
}

// Load current settings
$stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row->setting_key] = $row->setting_value;
}

$pageTitle = 'Settings';
$activeNav = 'settings';
require __DIR__ . '/includes/header_app.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <div class="breadcrumb">
            <a href="dashboard.php">Home</a>
            <span>&rsaquo;</span>
            <span class="current">Settings</span>
        </div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Shop information and account preferences</p>
    </div>
</div>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success" role="alert"><i class="ph-bold ph-check-circle"></i> <?= h($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
    <div class="alert alert-danger" role="alert"><i class="ph-bold ph-warning"></i> <?= h($msg) ?></div>
<?php endif; ?>

<div class="grid-2">

    <!-- Shop Information -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Shop Information</span>
        </div>
        <div class="card-body">
            <form method="POST" action="settings.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="shop">

                <div class="form-group">
                    <label class="form-label" for="shop-name">Shop Name</label>
                    <input type="text" id="shop-name" name="shop_name" class="form-control"
                           value="<?= h($settings['shop_name'] ?? 'Takines Labada') ?>" required aria-required="true" maxlength="100">
                </div>

                <div class="form-group">
                    <label class="form-label" for="shop-address">Address</label>
                    <input type="text" id="shop-address" name="shop_address" class="form-control"
                           value="<?= h($settings['shop_address'] ?? '') ?>" maxlength="255">
                </div>

                <div class="form-group">
                    <label class="form-label" for="shop-contact">Contact Number</label>
                    <input type="text" id="shop-contact" name="shop_contact" class="form-control"
                           value="<?= h($settings['shop_contact'] ?? '') ?>" maxlength="50">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check" aria-hidden="true"></i>
                    Save Shop Info
                </button>
            </form>
        </div>
    </div>

    <!-- Change Password -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Change Password</span>
        </div>
        <div class="card-body">
            <form method="POST" action="settings.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="password">

                <div class="form-group">
                    <label class="form-label" for="current-password">Current Password</label>
                    <input type="password" id="current-password" name="current_password" class="form-control" required aria-required="true">
                </div>

                <div class="form-group">
                    <label class="form-label" for="new-password">New Password</label>
                    <input type="password" id="new-password" name="new_password" class="form-control" required aria-required="true" minlength="8">
                    <small class="text-muted">At least 8 characters.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm-password">Confirm New Password</label>
                    <input type="password" id="confirm-password" name="confirm_password" class="form-control" required aria-required="true" minlength="8">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check" aria-hidden="true"></i>
                    Update Password
                </button>
            </form>
        </div>
    </div>

</div>

<?php
require __DIR__ . '/includes/footer_app.php';
