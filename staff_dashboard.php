<?php
/**
 * staff_dashboard.php
 * Takines Labada Hub — Staff Portal
 *
 * REQUIRED DB MIGRATION (run migrate.sql once before deploying):
 *   ALTER TABLE `sales`
 *     ADD COLUMN `customer_name` VARCHAR(100) DEFAULT NULL AFTER `recorded_by`,
 *     ADD COLUMN `status` ENUM('pending','done') NOT NULL DEFAULT 'done' AFTER `customer_name`;
 */
require_once __DIR__ . '/config.php';
require_role('staff');

$user = current_user();

// Price per load per service type (₱) — defined globally so PHP & JS share the same table
$servicePrices = [
    'Wash + Dry + Fold' => 120,
    'Wash + Dry'        => 100,
    'Wash Only'         => 70,
];

// ------------------------------------------------------------
// HANDLE MARK-AS-DONE (AJAX POST)
// Detect CSRF field name dynamically from the token function
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_done') {
    // verify_csrf() checks $_POST — it will find the token regardless of key name
    verify_csrf();
    $saleId = (int)($_POST['sale_id'] ?? 0);
    if ($saleId > 0) {
        $stmt = $pdo->prepare('UPDATE sales SET status = ? WHERE sale_id = ?');
        $stmt->execute(['done', $saleId]);
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    redirect('staff_dashboard.php');
}

// ------------------------------------------------------------
// HANDLE NEW TRANSACTION
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    verify_csrf();

    $allowedServices = array_keys($servicePrices);
    $transactionDate = $_POST['transaction_date'] ?? date('Y-m-d');
    $transactionTime = $_POST['transaction_time'] ?? date('H:i');
    $serviceType     = $_POST['service_type'] ?? '';
    $numLoads        = (int)($_POST['num_loads'] ?? 1);
    $fabricSpray     = isset($_POST['fabric_spray']) && $_POST['fabric_spray'] == '1' ? 1 : 0;
    $customerName    = trim($_POST['customer_name'] ?? '');

    // Auto-compute amount: loads × price per service (fabcon is free, no surcharge)
    $pricePerLoad    = $servicePrices[$serviceType] ?? 0;
    $amountPaid      = $numLoads * $pricePerLoad;
    $cashOnHand      = 0; // no longer collected from staff

    $errors = [];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transactionDate)) {
        $errors[] = 'A valid transaction date is required.';
    }
    if (!in_array($serviceType, $allowedServices, true)) {
        $errors[] = 'Please select a valid service type.';
    }
    if ($numLoads < 1 || $numLoads > 99) {
        $errors[] = 'Number of loads must be between 1 and 99 (2 digits max).';
    }
    if ($customerName === '') {
        $errors[] = 'Customer name is required.';
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO sales
                (transaction_date, transaction_time, service_type, num_loads,
                 fabric_spray, amount_paid, cash_on_hand, recorded_by, customer_name, status)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ');
        $stmt->execute([
            $transactionDate, $transactionTime, $serviceType, $numLoads,
            $fabricSpray, $amountPaid, $cashOnHand, $user->id, $customerName, 'pending'
        ]);
        flash('success', 'Transaction recorded. Mark it as done when laundry is finished!');
    }

    redirect('staff_dashboard.php');
}

// ------------------------------------------------------------
// IN-PROGRESS (pending) — today
// ------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT s.*, u.name AS recorded_by_name
    FROM sales s
    LEFT JOIN users u ON u.id = s.recorded_by
    WHERE s.transaction_date = CURDATE() AND s.status = 'pending'
    ORDER BY s.transaction_time ASC
");
$stmt->execute();
$pendingTransactions = $stmt->fetchAll();

// ------------------------------------------------------------
// COMPLETED — today
// ------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT s.*, u.name AS recorded_by_name
    FROM sales s
    LEFT JOIN users u ON u.id = s.recorded_by
    WHERE s.transaction_date = CURDATE() AND s.status = 'done'
    ORDER BY s.transaction_time DESC
");
$stmt->execute();
$todaysTransactions = $stmt->fetchAll();

$todayLoadsTotal = array_sum(array_map(fn($t) => (int)$t->num_loads, $todaysTransactions));
$allTodayCount   = count($todaysTransactions) + count($pendingTransactions);

// ------------------------------------------------------------
// PREVIOUS LOADS — all records before today (full history)
// ------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT s.*, u.name AS recorded_by_name
    FROM sales s
    LEFT JOIN users u ON u.id = s.recorded_by
    WHERE s.transaction_date < CURDATE()
    ORDER BY s.transaction_date DESC, s.transaction_time DESC
");
$stmt->execute();
$previousTransactions = $stmt->fetchAll();

// ------------------------------------------------------------
// Expose CSRF token + field name to JS so the AJAX call works
// regardless of what csrf_field() names the input.
// ------------------------------------------------------------
// We render a temporary hidden field and read its name in JS.
// csrf_field() returns something like:
//   <input type="hidden" name="csrf_token" value="abc123">
// We just need to capture what it outputs.
ob_start();
echo csrf_field();
$csrfFieldHtml = ob_get_clean();
// Extract name="..." and value="..."
preg_match('/name=["\']([^"\']+)["\']/', $csrfFieldHtml, $nameMatch);
preg_match('/value=["\']([^"\']+)["\']/', $csrfFieldHtml, $valueMatch);
$csrfFieldName  = $nameMatch[1]  ?? 'csrf_token';
$csrfFieldValue = $valueMatch[1] ?? '';

$pageTitle = 'Staff Portal';
require __DIR__ . '/includes/header_staff.php';
?>

<div class="page-content">

    <!-- Page header -->
    <div class="page-header">
        <div>
            <div class="breadcrumb">
                <span class="current">Staff Portal</span>
            </div>
            <h1 class="page-title">Welcome, <?= h(explode(' ', $user->name)[0]) ?>! 👋</h1>
            <p class="page-subtitle"><?= date('l, F j, Y') ?> — Record laundry loads as they come in</p>
        </div>
        <button class="btn btn-primary" id="btn-add-transaction" aria-haspopup="dialog">
            <i class="ph-bold ph-plus" aria-hidden="true"></i>
            + Record New Load
        </button>
    </div>

    <!-- Flash messages -->
    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success" role="alert" aria-live="polite">
            <i class="ph-bold ph-check-circle" aria-hidden="true"></i> <?= h($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger" role="alert" aria-live="polite">
            <i class="ph-bold ph-warning" aria-hidden="true"></i> <?= h($msg) ?>
        </div>
    <?php endif; ?>

    <!-- Summary stats -->
    <div class="grid-2 mb-lg">
        <div class="stat-card green">
            <div class="stat-card-label">Today's Transactions</div>
            <div class="stat-card-value"><?= $allTodayCount ?></div>
            <div class="stat-card-sub"><?= date('F j, Y') ?></div>
        </div>
        <div class="stat-card blue">
            <div class="stat-card-label">Today's Loads (Done)</div>
            <div class="stat-card-value"><?= (int)$todayLoadsTotal ?></div>
            <div class="stat-card-sub"><?= (int)$todayLoadsTotal * 7 ?> kg total</div>
        </div>
    </div>

    <!-- =====================================================
         IN-PROGRESS QUEUE
    ====================================================== -->
    <?php if (!empty($pendingTransactions)): ?>
    <div class="card mb-lg" style="border: 2px solid var(--color-warning, #f59e0b);">
        <div class="card-header" style="background: var(--color-warning-bg, #fffbeb);">
            <span class="card-title" style="color: var(--color-warning-dark, #b45309);">
                <i class="ph-bold ph-clock" aria-hidden="true"></i>
                In Progress — <?= count($pendingTransactions) ?> batch<?= count($pendingTransactions) !== 1 ? 'es' : '' ?> pending
            </span>
            <span style="font-size:12px; color:var(--color-text-muted);">
                Check the box once the customer's laundry is finished
            </span>
        </div>
        <div class="table-container" style="border:none; border-radius:0;">
            <table class="data-table" aria-label="In-progress laundry loads">
                <thead>
                    <tr>
                        <th scope="col" style="width:52px; text-align:center;">Done?</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Time In</th>
                        <th scope="col">Service</th>
                        <th scope="col">Loads (7kg)</th>
                        <th scope="col">Fabcon</th>
                        <th scope="col">Recorded By</th>
                    </tr>
                </thead>
                <tbody id="pending-tbody">
                    <?php
                    $svcColors = [
                        'Wash + Dry + Fold' => 'green',
                        'Wash + Dry'        => 'blue',
                        'Wash Only'         => 'orange',
                    ];
                    foreach ($pendingTransactions as $tx):
                        $color = $svcColors[$tx->service_type] ?? 'gray';
                    ?>
                    <tr id="pending-row-<?= (int)$tx->sale_id ?>" style="background: var(--color-warning-bg, #fffbeb);">
                        <td style="text-align:center;">
                            <input
                                type="checkbox"
                                class="done-checkbox"
                                data-id="<?= (int)$tx->sale_id ?>"
                                data-customer="<?= h($tx->customer_name ?? 'Customer') ?>"
                                aria-label="Mark <?= h($tx->customer_name ?? 'this load') ?> as done"
                                style="width:18px; height:18px; cursor:pointer; accent-color:var(--color-success,#16a34a);"
                            >
                        </td>
                        <td><strong><?= h($tx->customer_name ?? '—') ?></strong></td>
                        <td><?= date('h:i A', strtotime($tx->transaction_time)) ?></td>
                        <td><span class="badge badge-<?= $color ?>"><?= h($tx->service_type) ?></span></td>
                        <td>
                            <?= (int)$tx->num_loads ?> load<?= $tx->num_loads > 1 ? 's' : '' ?>
                            <span class="text-muted text-sm">(<?= (int)$tx->num_loads * 7 ?> kg)</span>
                        </td>
                        <td>
                            <span class="badge <?= $tx->fabric_spray ? 'badge-teal' : 'badge-gray' ?>">
                                <?= $tx->fabric_spray ? 'Yes' : 'No' ?>
                            </span>
                        </td>
                        <td><span class="badge badge-gray"><?= h($tx->recorded_by_name ?? 'Unknown') ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- =====================================================
         COMPLETED LOADS
    ====================================================== -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Today's Recorded Loads</span>
        </div>
        <div class="table-container" style="border:none; border-radius:0;">
            <table class="data-table" aria-label="Today's completed transactions">
                <thead>
                    <tr>
                        <th scope="col">Customer</th>
                        <th scope="col">Time</th>
                        <th scope="col">Service</th>
                        <th scope="col">Loads (7kg)</th>
                        <th scope="col">Fabcon</th>
                        <th scope="col">Recorded By</th>
                    </tr>
                </thead>
                <tbody id="done-tbody">
                    <?php if (empty($todaysTransactions)): ?>
                        <tr id="done-empty-row">
                            <td colspan="6" style="text-align:center; padding: var(--spacing-lg); color:var(--color-text-muted);">
                                No loads completed yet today.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $svcColors = [
                            'Wash + Dry + Fold' => 'green',
                            'Wash + Dry'        => 'blue',
                            'Wash Only'         => 'orange',
                        ];
                        foreach ($todaysTransactions as $tx):
                            $color = $svcColors[$tx->service_type] ?? 'gray';
                        ?>
                        <tr>
                            <td>
                                <?php if (!empty($tx->customer_name)): ?>
                                    <strong><?= h($tx->customer_name) ?></strong>
                                <?php else: ?>
                                    <span style="color:var(--color-text-muted); font-style:italic;">No name recorded</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('h:i A', strtotime($tx->transaction_time)) ?></td>
                            <td><span class="badge badge-<?= $color ?>"><?= h($tx->service_type) ?></span></td>
                            <td>
                                <?= (int)$tx->num_loads ?> load<?= $tx->num_loads > 1 ? 's' : '' ?>
                                <span class="text-muted text-sm">(<?= (int)$tx->num_loads * 7 ?> kg)</span>
                            </td>
                            <td>
                                <span class="badge <?= $tx->fabric_spray ? 'badge-teal' : 'badge-gray' ?>">
                                    <?= $tx->fabric_spray ? 'Yes' : 'No' ?>
                                </span>
                            </td>
                            <td><span class="badge badge-gray"><?= h($tx->recorded_by_name ?? 'Unknown') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>


    <!-- =====================================================
         PREVIOUS LOADS HISTORY
    ====================================================== -->
    <div class="card mt-lg" style="margin-top: var(--spacing-lg, 24px);">
        <div class="card-header">
            <span class="card-title">Previous Loads History</span>
            <span style="font-size:12px; color:var(--color-text-muted);">
                All recorded loads before today
            </span>
        </div>
        <div class="table-container" style="border:none; border-radius:0;">
            <table class="data-table" aria-label="Previous loads history">
                <thead>
                    <tr>
                        <th scope="col">Customer</th>
                        <th scope="col">Date &amp; Time</th>
                        <th scope="col">Service</th>
                        <th scope="col">Loads (7kg)</th>
                        <th scope="col">Fabcon</th>
                        <th scope="col">Status</th>
                        <th scope="col">Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($previousTransactions)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding: var(--spacing-lg); color:var(--color-text-muted);">
                                No previous load history yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $svcColors = [
                            'Wash + Dry + Fold' => 'green',
                            'Wash + Dry'        => 'blue',
                            'Wash Only'         => 'orange',
                        ];
                        $prevDate = null;
                        foreach ($previousTransactions as $tx):
                            $color   = $svcColors[$tx->service_type] ?? 'gray';
                            $txDate  = $tx->transaction_date;
                        ?>
                        <?php if ($txDate !== $prevDate): $prevDate = $txDate; ?>
                        <tr>
                            <td colspan="7"
                                style="background:var(--color-bg-muted, #f3f4f6);
                                       font-size:12px; font-weight:700;
                                       color:var(--color-text-muted);
                                       padding: 6px 14px;
                                       letter-spacing:.04em;
                                       text-transform:uppercase;">
                                <?= date('l, F j, Y', strtotime($txDate)) ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td>
                                <?php if (!empty($tx->customer_name)): ?>
                                    <strong><?= h($tx->customer_name) ?></strong>
                                <?php else: ?>
                                    <span style="color:var(--color-text-muted); font-style:italic;">No name recorded</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <?= date('M j, Y', strtotime($txDate)) ?>
                                <span style="color:var(--color-text-muted); font-size:12px;">
                                    <?= date('h:i A', strtotime($tx->transaction_time)) ?>
                                </span>
                            </td>
                            <td><span class="badge badge-<?= $color ?>"><?= h($tx->service_type) ?></span></td>
                            <td>
                                <?= (int)$tx->num_loads ?> load<?= $tx->num_loads > 1 ? 's' : '' ?>
                                <span class="text-muted text-sm">(<?= (int)$tx->num_loads * 7 ?> kg)</span>
                            </td>
                            <td>
                                <span class="badge <?= $tx->fabric_spray ? 'badge-teal' : 'badge-gray' ?>">
                                    <?= $tx->fabric_spray ? 'Yes' : 'No' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= ($tx->status ?? 'done') === 'done' ? 'badge-green' : 'badge-orange' ?>">
                                    <?= ucfirst($tx->status ?? 'done') ?>
                                </span>
                            </td>
                            <td><span class="badge badge-gray"><?= h($tx->recorded_by_name ?? 'Unknown') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /.page-content -->


<!-- =====================================================
     RECORD NEW LOAD MODAL
====================================================== -->
<div class="modal-overlay" id="modal-add-transaction" style="display:none;"
     role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-dialog">

        <div class="modal-header">
            <div class="modal-title" id="modal-title">
                <span aria-hidden="true">🧺</span> Record New Load
            </div>
            <button class="modal-close" id="btn-close-modal" aria-label="Close modal">
                <i class="ph-bold ph-x" aria-hidden="true"></i>
            </button>
        </div>

        <form method="POST" action="staff_dashboard.php" id="form-add-transaction" novalidate>
            <?= csrf_field() ?>

            <div class="modal-body">

                <!-- Customer Name -->
                <div class="form-group">
                    <label class="form-label" for="tx-customer">
                        Customer Name <span style="color:var(--color-danger,#dc2626);">*</span>
                    </label>
                    <input
                        type="text"
                        id="tx-customer"
                        name="customer_name"
                        class="form-control"
                        placeholder="e.g. Juan dela Cruz"
                        required
                        aria-required="true"
                        maxlength="100"
                        autocomplete="off"
                    >
                </div>

                <!-- Date & Time — shown read-only, auto-stamped on submit -->
                <div class="form-group">
                    <label class="form-label">Date &amp; Time</label>
                    <div id="tx-datetime-display"
                         style="background:var(--color-bg-muted, #f3f4f6);
                                border:1px solid var(--color-border, #e5e7eb);
                                border-radius:var(--radius-md, 6px);
                                padding:9px 14px;
                                font-size:14px;
                                color:var(--color-text-muted, #6b7280);
                                font-weight:500;">
                        —
                    </div>
                    <input type="hidden" id="tx-date" name="transaction_date">
                    <input type="hidden" id="tx-time" name="transaction_time">
                </div>

                <!-- Service type -->
                <div class="form-group">
                    <label class="form-label">Service Type</label>
                    <div class="toggle-group" role="group" aria-label="Select service type">
                        <button type="button" class="toggle-btn active"
                                data-value="Wash + Dry + Fold" onclick="selectService(this)">
                            Wash + Dry + Fold
                        </button>
                        <button type="button" class="toggle-btn"
                                data-value="Wash + Dry" onclick="selectService(this)">
                            Wash + Dry
                        </button>
                        <button type="button" class="toggle-btn"
                                data-value="Wash Only" onclick="selectService(this)">
                            Wash Only
                        </button>
                    </div>
                    <input type="hidden" name="service_type" id="service-type-input" value="Wash + Dry + Fold">
                </div>

                <!-- Loads -->
                <div class="form-group">
                    <label class="form-label" for="tx-loads">Number of Loads</label>
                    <input type="number" id="tx-loads" name="num_loads" class="form-control"
                           value="1" min="1" max="99" maxlength="2" required aria-required="true" oninput="recalculate()">
                </div>
                <!-- Hidden computed fields — filled by JS before submit -->
                <input type="hidden" id="tx-amount" name="amount_paid" value="0">

                <!-- Fabric spray -->
                <div class="form-group">
                    <label class="form-label" for="tx-spray">Fabcon</label>
                    <select id="tx-spray" name="fabric_spray" class="form-control">
                        <option value="0">No — without fabcon</option>
                        <option value="1">Yes — with fabcon</option>
                    </select>
                </div>

                <!-- Summary bar — price only -->
                <div style="background:var(--color-info-bg); border-radius:var(--radius-md);
                            padding: 14px var(--spacing-md);
                            display:flex; justify-content:space-between; align-items:center;
                            font-size:13px; font-weight:600; gap:8px;">
                    <span style="color:var(--color-text-muted); font-weight:500;">Total Price</span>
                    <span style="color:var(--color-primary); font-size:18px;">
                        <strong id="summary-amount">₱120.00</strong>
                        <span id="summary-breakdown" style="font-size:11px; color:var(--color-text-muted); font-weight:400; margin-left:4px;">
                            (1 × ₱120)
                        </span>
                    </span>
                </div>

            </div><!-- /.modal-body -->

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="btn-cancel-modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check" aria-hidden="true"></i>
                    Save Transaction
                </button>
            </div>

        </form>
    </div>
</div>


<!-- DONE TOAST -->
<div id="toast-done" role="status" aria-live="polite"
     style="display:none; position:fixed; bottom:24px; right:24px;
            background:var(--color-success,#16a34a); color:#fff;
            padding:12px 20px; border-radius:var(--radius-md,8px);
            font-size:14px; font-weight:600;
            box-shadow:0 4px 16px rgba(0,0,0,.18);
            z-index:9999; align-items:center; gap:8px;">
    <i class="ph-bold ph-check-circle" aria-hidden="true"></i>
    <span id="toast-msg">Done!</span>
</div>


<?php
// Pass CSRF field name & value to JS safely
$jsFieldName  = json_encode($csrfFieldName);
$jsFieldValue = json_encode($csrfFieldValue);

$pageScripts = <<<HTML
<script>
// ── CSRF info injected from PHP ────────────────────────────────────────────
const CSRF_FIELD = {$jsFieldName};
const CSRF_VALUE = {$jsFieldValue};

// ── Price table (must match server-side price table) ──────────────────────
const SERVICE_PRICES = {
    'Wash + Dry + Fold': 120,
    'Wash + Dry':        100,
    'Wash Only':         70,
};
// Fabcon is a free add-on — no surcharge

// ── Modal ──────────────────────────────────────────────────────────────────
const modal     = document.getElementById('modal-add-transaction');
const btnOpen   = document.getElementById('btn-add-transaction');
const btnClose  = document.getElementById('btn-close-modal');
const btnCancel = document.getElementById('btn-cancel-modal');

function getNow() {
    const now = new Date();
    const hh  = String(now.getHours()).padStart(2, '0');
    const mm  = String(now.getMinutes()).padStart(2, '0');
    return hh + ':' + mm;
}

function getToday() {
    const now = new Date();
    const yyyy = now.getFullYear();
    const mm   = String(now.getMonth() + 1).padStart(2, '0');
    const dd   = String(now.getDate()).padStart(2, '0');
    return yyyy + '-' + mm + '-' + dd;
}

function stampDateTime() {
    const today = getToday();
    const now   = getNow();
    document.getElementById('tx-date').value = today;
    document.getElementById('tx-time').value = now;

    // Update the visible read-only display
    const display = document.getElementById('tx-datetime-display');
    if (display) {
        const [yyyy, mm, dd] = today.split('-');
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const monthName = months[parseInt(mm, 10) - 1];
        const [hh, mi]  = now.split(':');
        const h12 = parseInt(hh, 10) % 12 || 12;
        const ampm = parseInt(hh, 10) >= 12 ? 'PM' : 'AM';
        display.textContent = monthName + ' ' + parseInt(dd, 10) + ', ' + yyyy
                            + ' — ' + h12 + ':' + mi + ' ' + ampm;
    }
}

function openModal() {
    stampDateTime();
    modal.style.display = 'flex';
    document.getElementById('tx-customer').focus();
    recalculate();
}

// Re-stamp at the exact moment of submission so the time is precise
document.getElementById('form-add-transaction').addEventListener('submit', function () {
    stampDateTime();
});

function closeModal() { modal.style.display = 'none'; }

btnOpen.addEventListener('click', openModal);
btnClose.addEventListener('click', closeModal);
btnCancel.addEventListener('click', closeModal);
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

// ── Service toggle ─────────────────────────────────────────────────────────
function selectService(btn) {
    document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('service-type-input').value = btn.dataset.value;
    recalculate();
}

// ── Price recalculation ────────────────────────────────────────────────────
function recalculate() {
    const loads        = Math.max(1, parseInt(document.getElementById('tx-loads').value) || 1);
    const service      = document.getElementById('service-type-input').value;
    const pricePerLoad = SERVICE_PRICES[service] ?? 120;
    const total        = loads * pricePerLoad;

    document.getElementById('summary-amount').textContent =
        '\u20b1' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('summary-breakdown').textContent =
        '(' + loads + ' \u00d7 \u20b1' + pricePerLoad + ')';

    // Keep hidden field in sync so PHP receives the computed amount
    document.getElementById('tx-amount').value = total.toFixed(2);
}

// ── Enforce 2-digit max on loads input ────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const sprayEl = document.getElementById('tx-spray');
    if (sprayEl) sprayEl.addEventListener('change', recalculate);

    const loadsEl = document.getElementById('tx-loads');
    if (loadsEl) {
        loadsEl.addEventListener('input', function () {
            // Strip anything beyond 2 digits
            if (this.value.length > 2) this.value = this.value.slice(0, 2);
            // Clamp to 1–99
            const v = parseInt(this.value);
            if (v > 99) this.value = '99';
            if (v < 1 && this.value !== '') this.value = '1';
        });
    }

    recalculate();
});

// ── Toast helper ───────────────────────────────────────────────────────────
function showToast(msg) {
    const toast = document.getElementById('toast-done');
    document.getElementById('toast-msg').textContent = msg;
    toast.style.display = 'flex';
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { toast.style.display = 'none'; }, 3500);
}

// ── Mark-as-done checkbox ──────────────────────────────────────────────────
document.addEventListener('change', function (e) {
    if (!e.target.matches('.done-checkbox')) return;

    const checkbox  = e.target;
    const saleId    = checkbox.dataset.id;
    const customer  = checkbox.dataset.customer || 'Customer';
    const row       = document.getElementById('pending-row-' + saleId);

    // Prevent double-click while request is in flight
    checkbox.disabled = true;

    // Build POST body with the correct CSRF field name
    const body = new URLSearchParams();
    body.append('action',   'mark_done');
    body.append('sale_id',  saleId);
    body.append(CSRF_FIELD, CSRF_VALUE);

    fetch('staff_dashboard.php', {
        method:  'POST',
        headers: {
            'Content-Type':     'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: body.toString()
    })
    .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(data => {
        if (!data.success) throw new Error('Server returned failure');

        // Build a new "done" row from the pending row's cells
        // Pending cols: 0=checkbox, 1=customer, 2=time, 3=service, 4=loads, 5=spray, 6=recorded-by
        const cells  = row.querySelectorAll('td');
        const newRow = document.createElement('tr');
        newRow.innerHTML =
            '<td>' + cells[1].innerHTML + '</td>' +
            '<td>' + cells[2].textContent.trim() + '</td>' +
            '<td>' + cells[3].innerHTML + '</td>' +
            '<td>' + cells[4].innerHTML + '</td>' +
            '<td>' + cells[5].innerHTML + '</td>' +
            '<td>' + cells[6].innerHTML + '</td>';

        // Remove "no loads yet" placeholder if it exists
        const empty = document.getElementById('done-empty-row');
        if (empty) empty.remove();

        // Prepend to done table
        const doneTbody = document.getElementById('done-tbody');
        doneTbody.insertBefore(newRow, doneTbody.firstChild);

        // Fade out & remove pending row
        row.style.transition = 'opacity .3s ease';
        row.style.opacity = '0';
        setTimeout(() => {
            row.remove();
            // If pending table is now empty, hide it
            const pendingTbody = document.getElementById('pending-tbody');
            if (pendingTbody && pendingTbody.querySelectorAll('tr').length === 0) {
                const card = pendingTbody.closest('.card');
                if (card) card.style.display = 'none';
            }
        }, 320);

        showToast('\u2705 ' + customer + "'s laundry is done!");
    })
    .catch(err => {
        console.error('mark_done error:', err);
        // Re-enable checkbox so user can retry, or fall back to reload
        checkbox.disabled  = false;
        checkbox.checked   = false;
        // Hard reload as final fallback
        window.location.reload();
    });
});
</script>
HTML;
require __DIR__ . '/includes/footer_staff.php';