<?php
/**
 * settings.php
 * Takines Labada Hub — Settings
 * Owner only — shop information, service pricing, tax, receipt, and account password
 */
require_once __DIR__ . '/config.php';
require_role('owner');

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = $_POST['form'] ?? '';

    // ─── Shop Information ────────────────────────────────────────────────────
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
        $stmt->execute(['shop_name',    $shopName,    $shopName]);
        $stmt->execute(['shop_address', $shopAddress, $shopAddress]);
        $stmt->execute(['shop_contact', $shopContact, $shopContact]);

        flash('success', 'Shop information updated.');
        redirect('settings.php');
    }

    // ─── Service Pricing ─────────────────────────────────────────────────────
    if ($form === 'pricing') {
        $priceWDF        = trim($_POST['price_wash_dry_fold'] ?? '');
        $priceWD         = trim($_POST['price_wash_dry'] ?? '');
        $priceWO         = trim($_POST['price_wash_only'] ?? '');
        $priceFabricSpray = trim($_POST['price_fabric_spray'] ?? '');

        $errors = [];
        foreach ([
            'Wash + Dry + Fold' => $priceWDF,
            'Wash + Dry'        => $priceWD,
            'Wash Only'         => $priceWO,
            'Fabric Spray add-on' => $priceFabricSpray,
        ] as $label => $val) {
            if (!is_numeric($val) || (float)$val < 0) {
                $errors[] = "$label must be a valid non-negative number.";
            }
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('settings.php');
        }

        $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE setting_value = ?');
        $stmt->execute(['price_wash_dry_fold',  $priceWDF,         $priceWDF]);
        $stmt->execute(['price_wash_dry',        $priceWD,          $priceWD]);
        $stmt->execute(['price_wash_only',       $priceWO,          $priceWO]);
        $stmt->execute(['price_fabric_spray',    $priceFabricSpray, $priceFabricSpray]);

        flash('success', 'Service prices updated.');
        redirect('settings.php');
    }

    // ─── Tax Settings ─────────────────────────────────────────────────────────
    if ($form === 'tax') {
        $taxEnabled = isset($_POST['tax_enabled']) ? '1' : '0';
        $taxRate    = trim($_POST['tax_rate'] ?? '0');

        if (!is_numeric($taxRate) || (float)$taxRate < 0 || (float)$taxRate > 100) {
            flash('error', 'Tax rate must be a number between 0 and 100.');
            redirect('settings.php');
        }

        $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE setting_value = ?');
        $stmt->execute(['tax_enabled', $taxEnabled, $taxEnabled]);
        $stmt->execute(['tax_rate',    $taxRate,    $taxRate]);

        flash('success', 'Tax settings updated.');
        redirect('settings.php');
    }

    // ─── Receipt Settings ─────────────────────────────────────────────────────
    if ($form === 'receipt') {
        $showTaxOnReceipt   = isset($_POST['receipt_show_tax'])    ? '1' : '0';
        $showCashOnReceipt  = isset($_POST['receipt_show_cash'])   ? '1' : '0';
        $receiptFooter      = trim($_POST['receipt_footer'] ?? '');

        if (strlen($receiptFooter) > 255) {
            flash('error', 'Footer note must not exceed 255 characters.');
            redirect('settings.php');
        }

        $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE setting_value = ?');
        $stmt->execute(['receipt_show_tax',  $showTaxOnReceipt,  $showTaxOnReceipt]);
        $stmt->execute(['receipt_show_cash', $showCashOnReceipt, $showCashOnReceipt]);
        $stmt->execute(['receipt_footer',    $receiptFooter,     $receiptFooter]);

        flash('success', 'Receipt settings updated.');
        redirect('settings.php');
    }

    // ─── Change Password ──────────────────────────────────────────────────────
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

// ─── Load all settings ────────────────────────────────────────────────────────
$stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row->setting_key] = $row->setting_value;
}

// ─── Defaults (if keys don't exist yet) ──────────────────────────────────────
$defaults = [
    'shop_name'          => 'Takines Labada',
    'shop_address'       => '',
    'shop_contact'       => '',
    'price_wash_dry_fold'=> '100.00',
    'price_wash_dry'     => '80.00',
    'price_wash_only'    => '60.00',
    'price_fabric_spray' => '20.00',
    'tax_enabled'        => '0',
    'tax_rate'           => '0.00',
    'receipt_show_tax'   => '0',
    'receipt_show_cash'  => '1',
    'receipt_footer'     => 'Thank you for choosing Takines Labada!',
];
foreach ($defaults as $key => $val) {
    if (!isset($settings[$key])) {
        $settings[$key] = $val;
    }
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
        <p class="page-subtitle">Shop information, pricing, and account preferences</p>
    </div>
</div>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success" role="alert">
        <i class="ph-bold ph-check-circle"></i> <?= h($msg) ?>
    </div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
    <div class="alert alert-danger" role="alert">
        <i class="ph-bold ph-warning"></i> <?= h($msg) ?>
    </div>
<?php endif; ?>


<!-- ═══════════════════════════════════════════════════════════════════════════
     ROW 1 — Shop Info  |  Service Pricing
════════════════════════════════════════════════════════════════════════════ -->
<div class="grid-2">

    <!-- Shop Information -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="ph-bold ph-storefront" aria-hidden="true"></i>
                Shop Information
            </span>
        </div>
        <div class="card-body">
            <form method="POST" action="settings.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="shop">

                <div class="form-group">
                    <label class="form-label" for="shop-name">Shop Name <span class="text-danger">*</span></label>
                    <input type="text" id="shop-name" name="shop_name" class="form-control"
                           value="<?= h($settings['shop_name']) ?>"
                           required aria-required="true" maxlength="100">
                </div>

                <div class="form-group">
                    <label class="form-label" for="shop-address">Address</label>
                    <input type="text" id="shop-address" name="shop_address" class="form-control"
                           value="<?= h($settings['shop_address']) ?>" maxlength="255">
                </div>

                <div class="form-group">
                    <label class="form-label" for="shop-contact">Contact Number</label>
                    <input type="text" id="shop-contact" name="shop_contact" class="form-control"
                           value="<?= h($settings['shop_contact']) ?>" maxlength="50">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check" aria-hidden="true"></i>
                    Save Shop Info
                </button>
            </form>
        </div>
    </div>

    <!-- Service Pricing -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="ph-bold ph-tag" aria-hidden="true"></i>
                Service Pricing
            </span>
        </div>
        <div class="card-body">
            <form method="POST" action="settings.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="pricing">

                <p class="text-muted" style="margin-bottom:1rem; font-size:.85rem;">
                    Prices are <strong>per load</strong>. These are used when computing
                    the amount for each transaction.
                </p>

                <div class="form-group">
                    <label class="form-label" for="price-wdf">
                        Wash + Dry + Fold &nbsp;<small class="text-muted">(₱ / load)</small>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" id="price-wdf" name="price_wash_dry_fold"
                               class="form-control" min="0" step="0.01"
                               value="<?= h($settings['price_wash_dry_fold']) ?>"
                               required aria-required="true">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="price-wd">
                        Wash + Dry &nbsp;<small class="text-muted">(₱ / load)</small>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" id="price-wd" name="price_wash_dry"
                               class="form-control" min="0" step="0.01"
                               value="<?= h($settings['price_wash_dry']) ?>"
                               required aria-required="true">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="price-wo">
                        Wash Only &nbsp;<small class="text-muted">(₱ / load)</small>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" id="price-wo" name="price_wash_only"
                               class="form-control" min="0" step="0.01"
                               value="<?= h($settings['price_wash_only']) ?>"
                               required aria-required="true">
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" id="price-fabric-spray" name="price_fabric_spray"
                               class="form-control" min="0" step="0.01"
                               value="<?= h($settings['price_fabric_spray']) ?>"
                               required aria-required="true">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check" aria-hidden="true"></i>
                    Save Prices
                </button>
            </form>
        </div>
    </div>

</div><!-- /grid-2 row 1 -->


<!-- ═══════════════════════════════════════════════════════════════════════════
     ROW 2 — Tax Settings  |  Receipt Settings
════════════════════════════════════════════════════════════════════════════ -->
<div class="grid-2">

    <!-- Tax Settings -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="ph-bold ph-percent" aria-hidden="true"></i>
                Tax Settings
            </span>
        </div>
        <div class="card-body">
            <form method="POST" action="settings.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="tax">

                <div class="form-group">
                    <div class="form-check" style="display:flex; align-items:center; gap:.6rem;">
                        <input type="checkbox" id="tax-enabled" name="tax_enabled"
                               class="form-check-input"
                               <?= $settings['tax_enabled'] === '1' ? 'checked' : '' ?>
                               onchange="toggleTaxRate(this)">
                        <label class="form-check-label" for="tax-enabled">
                            Enable Tax / VAT on transactions
                        </label>
                    </div>
                </div>

                <div class="form-group" id="tax-rate-group"
                     style="<?= $settings['tax_enabled'] !== '1' ? 'opacity:.45; pointer-events:none;' : '' ?>">
                    <label class="form-label" for="tax-rate">
                        Tax Rate &nbsp;<small class="text-muted">(%)</small>
                    </label>
                    <div class="input-group">
                        <input type="number" id="tax-rate" name="tax_rate"
                               class="form-control" min="0" max="100" step="0.01"
                               value="<?= h($settings['tax_rate']) ?>">
                        <span class="input-group-text">%</span>
                    </div>
                    <small class="text-muted">
                        e.g. enter <strong>12</strong> for 12% VAT.
                        Applied on top of the subtotal.
                    </small>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check" aria-hidden="true"></i>
                    Save Tax Settings
                </button>
            </form>
        </div>
    </div>

    <!-- Receipt Settings -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="ph-bold ph-receipt" aria-hidden="true"></i>
                Receipt Settings
            </span>
        </div>
        <div class="card-body">
            <form method="POST" action="settings.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="receipt">

                <div class="form-group">
                    <div class="form-check" style="display:flex; align-items:center; gap:.6rem; margin-bottom:.75rem;">
                        <input type="checkbox" id="receipt-show-tax" name="receipt_show_tax"
                               class="form-check-input"
                               <?= $settings['receipt_show_tax'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="receipt-show-tax">
                            Show tax breakdown on receipt
                        </label>
                    </div>

                    <div class="form-check" style="display:flex; align-items:center; gap:.6rem;">
                        <input type="checkbox" id="receipt-show-cash" name="receipt_show_cash"
                               class="form-check-input"
                               <?= $settings['receipt_show_cash'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="receipt-show-cash">
                            Show cash on hand on receipt
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="receipt-footer">Receipt Footer Note</label>
                    <input type="text" id="receipt-footer" name="receipt_footer"
                           class="form-control"
                           value="<?= h($settings['receipt_footer']) ?>"
                           maxlength="255"
                           placeholder="e.g. Thank you for choosing Takines Labada!">
                    <small class="text-muted">Printed at the bottom of every receipt. Max 255 characters.</small>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check" aria-hidden="true"></i>
                    Save Receipt Settings
                </button>
            </form>
        </div>
    </div>

</div><!-- /grid-2 row 2 -->


<!-- ═══════════════════════════════════════════════════════════════════════════
     ROW 3 — Change Password  (full width)
════════════════════════════════════════════════════════════════════════════ -->
<div class="grid-2">

    <!-- Change Password -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="ph-bold ph-lock-key" aria-hidden="true"></i>
                Change Password
            </span>
        </div>
        <div class="card-body">
            <form method="POST" action="settings.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="password">

                <div class="form-group">
                    <label class="form-label" for="current-password">Current Password</label>
                    <input type="password" id="current-password" name="current_password"
                           class="form-control" required aria-required="true"
                           autocomplete="current-password">
                </div>

                <div class="form-group">
                    <label class="form-label" for="new-password">New Password</label>
                    <input type="password" id="new-password" name="new_password"
                           class="form-control" required aria-required="true" minlength="8"
                           autocomplete="new-password">
                    <small class="text-muted">At least 8 characters.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm-password">Confirm New Password</label>
                    <input type="password" id="confirm-password" name="confirm_password"
                           class="form-control" required aria-required="true" minlength="8"
                           autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check" aria-hidden="true"></i>
                    Update Password
                </button>
            </form>
        </div>
    </div>

    <!-- Pricing Preview (live) -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="ph-bold ph-calculator" aria-hidden="true"></i>
                Pricing Preview
            </span>
        </div>
        <div class="card-body">
            <p class="text-muted" style="font-size:.85rem; margin-bottom:1rem;">
                Live preview of how a transaction amount is computed using the
                current prices and tax settings.
            </p>

            <div class="form-group">
                <label class="form-label" for="preview-service">Service</label>
                <select id="preview-service" class="form-control" onchange="updatePreview()">
                    <option value="<?= h($settings['price_wash_dry_fold']) ?>">Wash + Dry + Fold — ₱<?= h(number_format((float)$settings['price_wash_dry_fold'], 2)) ?>/load</option>
                    <option value="<?= h($settings['price_wash_dry']) ?>">Wash + Dry — ₱<?= h(number_format((float)$settings['price_wash_dry'], 2)) ?>/load</option>
                    <option value="<?= h($settings['price_wash_only']) ?>">Wash Only — ₱<?= h(number_format((float)$settings['price_wash_only'], 2)) ?>/load</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="preview-loads">Number of Loads</label>
                <input type="number" id="preview-loads" class="form-control"
                       min="1" value="1" oninput="updatePreview()">
            </div>

            <div class="form-group">
            </div>

            <table class="table table-sm" style="margin-top:.5rem;" aria-label="Pricing breakdown">
                <tbody>
                    <tr>
                        <td class="text-muted">Subtotal</td>
                        <td class="text-end fw-semibold" id="preview-subtotal">₱0.00</td>
                    </tr>
                    <tr id="preview-tax-row" style="<?= $settings['tax_enabled'] !== '1' ? 'display:none' : '' ?>">
                        <td class="text-muted">Tax (<?= h($settings['tax_rate']) ?>%)</td>
                        <td class="text-end" id="preview-tax">₱0.00</td>
                    </tr>
                    <tr style="border-top: 2px solid var(--border-color);">
                        <td><strong>Total</strong></td>
                        <td class="text-end"><strong id="preview-total">₱0.00</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /grid-2 row 3 -->


<script>
/* ── Tax rate toggle ─────────────────────────────────────────────── */
function toggleTaxRate(checkbox) {
    const group = document.getElementById('tax-rate-group');
    group.style.opacity        = checkbox.checked ? '1'    : '0.45';
    group.style.pointerEvents  = checkbox.checked ? 'auto' : 'none';
}

/* ── Pricing Preview ─────────────────────────────────────────────── */
const TAX_ENABLED = <?= json_encode($settings['tax_enabled'] === '1') ?>;
const TAX_RATE    = <?= json_encode((float)$settings['tax_rate']) ?>;
const SPRAY_PRICE = <?= json_encode((float)$settings['price_fabric_spray']) ?>;

function fmt(n) {
    return '₱' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function updatePreview() {
    const pricePerLoad = parseFloat(document.getElementById('preview-service').value) || 0;
    const loads        = parseInt(document.getElementById('preview-loads').value, 10)  || 0;
    const withSpray    = document.getElementById('preview-spray').checked;

    const subtotal = (pricePerLoad * loads) + (withSpray ? SPRAY_PRICE : 0);
    const taxAmt   = TAX_ENABLED ? subtotal * (TAX_RATE / 100) : 0;
    const total    = subtotal + taxAmt;

    document.getElementById('preview-subtotal').textContent = fmt(subtotal);
    document.getElementById('preview-tax').textContent      = fmt(taxAmt);
    document.getElementById('preview-total').textContent    = fmt(total);
}

// Run once on load
updatePreview();
</script>

<?php
require __DIR__ . '/includes/footer_app.php';       