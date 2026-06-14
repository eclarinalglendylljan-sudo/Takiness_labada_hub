<?php
/**
 * sales.php
 * Takines Labada Hub — Sales Transactions Module
 * Record and view daily laundry sales. 1 load = 7 kg.
 */
require_once __DIR__ . '/config.php';
require_role('owner');

$user = current_user();

// ------------------------------------------------------------
// HANDLE FORM SUBMISSIONS (Add / Edit / Delete)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $method = $_POST['_method'] ?? 'POST';

    if ($method === 'DELETE') {
        $id = (int)($_POST['sale_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM sales WHERE sale_id = ?');
        $stmt->execute([$id]);
        flash('success', 'Transaction deleted successfully.');
        redirect('sales.php');
    }

    // Validate shared fields for both Add and Edit
    $allowedServices = ['Wash + Dry + Fold', 'Wash + Dry', 'Wash Only'];
    $transactionDate = $_POST['transaction_date'] ?? '';
    $transactionTime = $_POST['transaction_time'] ?? '';
    $serviceType     = $_POST['service_type'] ?? '';
    $numLoads        = (int)($_POST['num_loads'] ?? 1);
    $fabricSpray     = isset($_POST['fabric_spray']) && $_POST['fabric_spray'] == '1' ? 1 : 0;
    $amountPaid      = (float)($_POST['amount_paid'] ?? 0);
    $cashOnHand      = (float)($_POST['cash_on_hand'] ?? 0);

    $errors = [];
    if ($transactionDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $transactionDate)) {
        $errors[] = 'A valid transaction date is required.';
    }
    if ($transactionTime === '') {
        $errors[] = 'A transaction time is required.';
    }
    if (!in_array($serviceType, $allowedServices, true)) {
        $errors[] = 'Please select a valid service type.';
    }
    if ($numLoads < 1 || $numLoads > 99) {
        $errors[] = 'Number of loads must be between 1 and 99.';
    }
    if ($amountPaid < 0) {
        $errors[] = 'Amount paid cannot be negative.';
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect('sales.php');
    }

    if ($method === 'PUT') {
        $id = (int)($_POST['sale_id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE sales SET transaction_date=?, transaction_time=?, service_type=?, num_loads=?, fabric_spray=?, amount_paid=?, cash_on_hand=? WHERE sale_id=?');
        $stmt->execute([$transactionDate, $transactionTime, $serviceType, $numLoads, $fabricSpray, $amountPaid, $cashOnHand, $id]);
        flash('success', 'Transaction updated successfully.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO sales (transaction_date, transaction_time, service_type, num_loads, fabric_spray, amount_paid, cash_on_hand, recorded_by) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$transactionDate, $transactionTime, $serviceType, $numLoads, $fabricSpray, $amountPaid, $cashOnHand, $user->id]);
        flash('success', 'Transaction recorded successfully.');
    }

    redirect('sales.php');
}

// ------------------------------------------------------------
// FILTERS
// ------------------------------------------------------------
$period = $_GET['period'] ?? 'today';
$date   = $_GET['date']   ?? date('Y-m-d');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$where  = [];
$params = [];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

switch ($period) {
    case 'week':
        $where[] = 'transaction_date BETWEEN DATE_SUB(?, INTERVAL 6 DAY) AND ?';
        $params[] = $date;
        $params[] = $date;
        break;
    case 'month':
        $where[] = 'YEAR(transaction_date) = YEAR(?) AND MONTH(transaction_date) = MONTH(?)';
        $params[] = $date;
        $params[] = $date;
        break;
    default: // today
        $where[] = 'transaction_date = ?';
        $params[] = $date;
        break;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Total count for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM sales $whereSql");
$stmt->execute($params);
$totalRecords = (int)$stmt->fetch()->cnt;
$totalPages   = max(1, (int)ceil($totalRecords / $perPage));
$page         = min($page, $totalPages);
$offset       = ($page - 1) * $perPage;

// Fetch transactions (with recorder name)
$sql = "SELECT s.*, u.name AS recorded_by_name
        FROM sales s
        LEFT JOIN users u ON u.id = s.recorded_by
        $whereSql
        ORDER BY s.transaction_date DESC, s.transaction_time DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// ------------------------------------------------------------
// STAT CARDS
// ------------------------------------------------------------
$stmt = $pdo->query("SELECT COALESCE(SUM(amount_paid),0) AS total, COUNT(*) AS cnt FROM sales WHERE transaction_date = CURDATE()");
$row = $stmt->fetch();
$todayTotal   = (float)$row->total;
$todayTxCount = (int)$row->cnt;

$weekStart = date('Y-m-d', strtotime('monday this week'));
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) AS total FROM sales WHERE transaction_date BETWEEN ? AND DATE_ADD(?, INTERVAL 6 DAY)");
$stmt->execute([$weekStart, $weekStart]);
$weekTotal = (float)$stmt->fetch()->total;

$prevWeekStart = date('Y-m-d', strtotime($weekStart . ' -7 day'));
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) AS total FROM sales WHERE transaction_date BETWEEN ? AND DATE_ADD(?, INTERVAL 6 DAY)");
$stmt->execute([$prevWeekStart, $prevWeekStart]);
$prevWeekTotal = (float)$stmt->fetch()->total;
$weekTrendPct = $prevWeekTotal > 0 ? round((($weekTotal - $prevWeekTotal) / $prevWeekTotal) * 100) : ($weekTotal > 0 ? 100 : 0);

$stmt = $pdo->query("SELECT COALESCE(SUM(amount_paid),0) AS total FROM sales WHERE YEAR(transaction_date)=YEAR(CURDATE()) AND MONTH(transaction_date)=MONTH(CURDATE())");
$monthTotal = (float)$stmt->fetch()->total;

$stmt = $pdo->query("SELECT cash_on_hand FROM sales ORDER BY transaction_date DESC, transaction_time DESC, sale_id DESC LIMIT 1");
$lastCash = $stmt->fetch();
$cashOnHand = $lastCash ? (float)$lastCash->cash_on_hand : 0;

$pageTitle = 'Sales';
$activeNav = 'sales';
require __DIR__ . '/includes/header_app.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <div class="breadcrumb">
            <a href="dashboard.php">Home</a>
            <span>&rsaquo;</span>
            <span class="current">Sales</span>
        </div>
        <h1 class="page-title">Sales Transactions</h1>
        <p class="page-subtitle">Record and view daily laundry sales</p>
    </div>
    <button class="btn btn-primary" id="btn-add-transaction" aria-haspopup="dialog">
        <i class="ph-bold ph-plus" aria-hidden="true"></i>
        + Add Transaction
    </button>
</div>

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

<!-- SALES STAT CARDS — 4 columns -->
<div class="grid-4 mb-lg">
    <div class="stat-card green">
        <div class="stat-card-label">Today's Total</div>
        <div class="stat-card-value"><?= peso($todayTotal) ?></div>
        <div class="stat-card-sub"><?= (int)$todayTxCount ?> transactions</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-card-label">This Week</div>
        <div class="stat-card-value"><?= peso($weekTotal) ?></div>
        <div class="stat-card-trend <?= $weekTrendPct >= 0 ? 'trend-up' : 'trend-down' ?>">
            <i class="ph-bold ph-trend-<?= $weekTrendPct >= 0 ? 'up' : 'down' ?>"></i> <?= h(abs($weekTrendPct)) ?>%
        </div>
    </div>
    <div class="stat-card teal">
        <div class="stat-card-label">This Month</div>
        <div class="stat-card-value"><?= peso($monthTotal) ?></div>
        <div class="stat-card-sub"><?= date('F Y') ?></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-card-label">Cash on Hand</div>
        <div class="stat-card-value"><?= peso($cashOnHand) ?></div>
        <div class="stat-card-sub">Latest recorded</div>
    </div>
</div>

<!-- TRANSACTIONS TABLE -->
<div class="card">

    <!-- Table Toolbar -->
    <div style="padding: var(--spacing-md) var(--spacing-lg); border-bottom: 1px solid var(--color-border);
                display:flex; align-items:center; gap: var(--spacing-md); flex-wrap:wrap;">

        <div class="period-toggle" role="group" aria-label="Period filter">
            <a class="period-btn <?= $period === 'today' ? 'active' : '' ?>" href="?period=today&date=<?= h($date) ?>">Today</a>
            <a class="period-btn <?= $period === 'week'  ? 'active' : '' ?>" href="?period=week&date=<?= h($date) ?>">Week</a>
            <a class="period-btn <?= $period === 'month' ? 'active' : '' ?>" href="?period=month&date=<?= h($date) ?>">Month</a>
        </div>

        <form method="GET" style="margin:0;">
            <input type="hidden" name="period" value="<?= h($period) ?>">
            <input type="date" class="form-control" style="width:auto;" name="date" value="<?= h($date) ?>"
                   id="date-filter" aria-label="Filter by specific date" onchange="this.form.submit()">
        </form>

    </div>

    <!-- Data Table -->
    <div class="table-container" style="border:none; border-radius:0;">
        <table class="data-table" aria-label="Sales transactions">
            <thead>
                <tr>
                    <th scope="col">Date &amp; Time</th>
                    <th scope="col">Service</th>
                    <th scope="col">Loads (7kg)</th>
                    <th scope="col">Fabric Spray</th>
                    <th scope="col">Amount Paid</th>
                    <th scope="col">Cash on Hand</th>
                    <th scope="col">Recorded By</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding: var(--spacing-lg); color:var(--color-text-muted);">
                            No transactions found for this period.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): ?>
                        <?php
                            $svcColors = [
                                'Wash + Dry + Fold' => 'green',
                                'Wash + Dry'        => 'blue',
                                'Wash Only'         => 'orange',
                            ];
                            $color = $svcColors[$tx->service_type] ?? 'gray';
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:600; font-size:13px;">
                                    <?= date('M j, Y', strtotime($tx->transaction_date)) ?>
                                </div>
                                <div class="text-muted text-sm"><?= date('h:i A', strtotime($tx->transaction_time)) ?></div>
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
                            <td><span style="font-weight:700; color:var(--color-success);"><?= peso($tx->amount_paid) ?></span></td>
                            <td><?= peso($tx->cash_on_hand) ?></td>
                            <td><span class="badge badge-gray"><?= h($tx->recorded_by_name ?? 'Unknown') ?></span></td>
                            <td>
                                <button class="action-btn edit"
                                        onclick='openEditModal(<?= json_encode([
                                            "sale_id" => $tx->sale_id,
                                            "transaction_date" => $tx->transaction_date,
                                            "transaction_time" => substr($tx->transaction_time, 0, 5),
                                            "service_type" => $tx->service_type,
                                            "num_loads" => (int)$tx->num_loads,
                                            "fabric_spray" => (int)$tx->fabric_spray,
                                            "amount_paid" => (float)$tx->amount_paid,
                                            "cash_on_hand" => (float)$tx->cash_on_hand,
                                        ]) ?>)'
                                        aria-label="Edit transaction <?= (int)$tx->sale_id ?>"
                                        title="Edit">
                                    <i class="ph-bold ph-pencil-simple" aria-hidden="true"></i>
                                </button>
                                <form method="POST" action="sales.php" style="display:inline;"
                                      onsubmit="return confirm('Delete this transaction? This cannot be undone.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_method" value="DELETE">
                                    <input type="hidden" name="sale_id" value="<?= (int)$tx->sale_id ?>">
                                    <button type="submit" class="action-btn delete"
                                            aria-label="Delete transaction <?= (int)$tx->sale_id ?>" title="Delete">
                                        <i class="ph-bold ph-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="pagination">
        <span>
            Showing <?= $totalRecords > 0 ? ($offset + 1) : 0 ?>
            &ndash; <?= min($offset + $perPage, $totalRecords) ?>
            of <?= $totalRecords ?> records
        </span>
        <?php $qs = ['period' => $period, 'date' => $date]; ?>
        <a class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
           href="?<?= http_build_query(array_merge($qs, ['page' => max(1, $page - 1)])) ?>">&lsaquo; Prev</a>
        <a class="page-btn <?= $page >= $totalPages ? 'disabled' : 'active' ?>"
           href="?<?= http_build_query(array_merge($qs, ['page' => min($totalPages, $page + 1)])) ?>">Next &rsaquo;</a>
    </div>

</div><!-- /.card -->


<!-- ADD TRANSACTION MODAL -->
<div class="modal-overlay" id="modal-add-transaction" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-dialog">

        <div class="modal-header">
            <div class="modal-title" id="modal-title">
                <span aria-hidden="true">💰</span>
                New Sales Transaction
            </div>
            <button class="modal-close" id="btn-close-modal" aria-label="Close modal">
                <i class="ph-bold ph-x" aria-hidden="true"></i>
            </button>
        </div>

        <form method="POST" action="sales.php" id="form-add-transaction" novalidate>
            <?= csrf_field() ?>

            <div class="modal-body">

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="tx-date">Transaction Date</label>
                        <input type="date" id="tx-date" name="transaction_date" class="form-control"
                               value="<?= date('Y-m-d') ?>" required aria-required="true">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="tx-time">Time</label>
                        <input type="time" id="tx-time" name="transaction_time" class="form-control"
                               value="<?= date('H:i') ?>" required aria-required="true">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Service Type</label>
                    <div class="toggle-group" role="group" aria-label="Select service type">
                        <button type="button" class="toggle-btn active" data-value="Wash + Dry + Fold" onclick="selectService(this)">
                            Wash + Dry + Fold
                        </button>
                        <button type="button" class="toggle-btn" data-value="Wash + Dry" onclick="selectService(this)">
                            Wash + Dry
                        </button>
                        <button type="button" class="toggle-btn" data-value="Wash Only" onclick="selectService(this)">
                            Wash Only
                        </button>
                    </div>
                    <input type="hidden" name="service_type" id="service-type-input" value="Wash + Dry + Fold">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:var(--spacing-md);">
                    <div class="form-group">
                        <label class="form-label" for="tx-loads">No. of Loads</label>
                        <input type="number" id="tx-loads" name="num_loads" class="form-control"
                               value="1" min="1" max="99" required aria-required="true" oninput="recalculate()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="tx-amount">Amount Paid (₱)</label>
                        <input type="number" id="tx-amount" name="amount_paid" class="form-control"
                               value="0" min="0" step="0.01" required aria-required="true" oninput="recalculate()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="tx-cash">Cash on Hand (₱)</label>
                        <input type="number" id="tx-cash" name="cash_on_hand" class="form-control"
                               value="0" min="0" step="0.01" required aria-required="true">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="tx-spray">Fabric Spray Used</label>
                        <select id="tx-spray" name="fabric_spray" class="form-control">
                            <option value="0">No — without spray</option>
                            <option value="1">Yes — with spray</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Recorded By</label>
                        <div class="form-control" style="background:var(--color-bg); color:var(--color-text-secondary);">
                            <?= h($user->name) ?>
                        </div>
                    </div>
                </div>

                <div style="background:var(--color-info-bg); border-radius:var(--radius-md);
                            padding: 12px var(--spacing-md);
                            display:flex; justify-content:space-between; align-items:center;
                            font-size:13px; font-weight:600;">
                    <span>
                        Total Weight:
                        <strong id="summary-weight">7 kg</strong>
                        <span id="summary-loads-text" style="color:var(--color-text-muted);">(1 load &times; 7 kg)</span>
                    </span>
                    <span style="color:var(--color-primary);">
                        Amount: <strong id="summary-amount">₱0.00</strong>
                    </span>
                </div>

            </div><!-- /.modal-body -->

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="btn-cancel-modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-save-transaction">
                    <i class="ph-bold ph-check" aria-hidden="true"></i>
                    Save Transaction
                </button>
            </div>

        </form>
    </div>
</div><!-- /.modal-overlay -->


<!-- EDIT TRANSACTION MODAL -->
<div class="modal-overlay" id="modal-edit-transaction" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="edit-modal-title">
    <div class="modal-dialog">

        <div class="modal-header">
            <div class="modal-title" id="edit-modal-title">
                <span aria-hidden="true">✏️</span>
                Edit Sales Transaction
            </div>
            <button class="modal-close" id="btn-close-edit-modal" aria-label="Close modal">
                <i class="ph-bold ph-x" aria-hidden="true"></i>
            </button>
        </div>

        <form method="POST" action="sales.php" id="form-edit-transaction" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="PUT">
            <input type="hidden" name="sale_id" id="edit-sale-id">

            <div class="modal-body">

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="edit-tx-date">Transaction Date</label>
                        <input type="date" id="edit-tx-date" name="transaction_date" class="form-control" required aria-required="true">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-tx-time">Time</label>
                        <input type="time" id="edit-tx-time" name="transaction_time" class="form-control" required aria-required="true">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-tx-service">Service Type</label>
                    <select id="edit-tx-service" name="service_type" class="form-control" required aria-required="true">
                        <option value="Wash + Dry + Fold">Wash + Dry + Fold</option>
                        <option value="Wash + Dry">Wash + Dry</option>
                        <option value="Wash Only">Wash Only</option>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:var(--spacing-md);">
                    <div class="form-group">
                        <label class="form-label" for="edit-tx-loads">No. of Loads</label>
                        <input type="number" id="edit-tx-loads" name="num_loads" class="form-control" min="1" max="99" required aria-required="true">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-tx-amount">Amount Paid (₱)</label>
                        <input type="number" id="edit-tx-amount" name="amount_paid" class="form-control" min="0" step="0.01" required aria-required="true">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-tx-cash">Cash on Hand (₱)</label>
                        <input type="number" id="edit-tx-cash" name="cash_on_hand" class="form-control" min="0" step="0.01" required aria-required="true">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-tx-spray">Fabric Spray Used</label>
                    <select id="edit-tx-spray" name="fabric_spray" class="form-control">
                        <option value="0">No — without spray</option>
                        <option value="1">Yes — with spray</option>
                    </select>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="btn-cancel-edit-modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check" aria-hidden="true"></i>
                    Update Transaction
                </button>
            </div>

        </form>
    </div>
</div><!-- /.modal-overlay -->

<?php
$pageScripts = <<<'HTML'
<script>
    // ===== Add modal =====
    const modal     = document.getElementById('modal-add-transaction');
    const btnOpen   = document.getElementById('btn-add-transaction');
    const btnClose  = document.getElementById('btn-close-modal');
    const btnCancel = document.getElementById('btn-cancel-modal');

    function openModal()  { modal.style.display = 'flex'; document.getElementById('tx-date').focus(); }
    function closeModal() { modal.style.display = 'none'; }

    btnOpen.addEventListener('click', openModal);
    btnClose.addEventListener('click', closeModal);
    btnCancel.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

    // Open automatically if URL ends with #add
    if (window.location.hash === '#add') openModal();

    // Service type toggle
    function selectService(btn) {
        document.querySelectorAll('#form-add-transaction .toggle-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('service-type-input').value = btn.dataset.value;
    }

    // Weight + amount summary (1 load = 7 kg)
    function recalculate() {
        const loads  = parseInt(document.getElementById('tx-loads').value) || 0;
        const amount = parseFloat(document.getElementById('tx-amount').value) || 0;
        const weight = loads * 7;

        document.getElementById('summary-weight').textContent = weight + ' kg';
        document.getElementById('summary-loads-text').textContent =
            `(${loads} load${loads !== 1 ? 's' : ''} \u00d7 7 kg)`;
        document.getElementById('summary-amount').textContent =
            '\u20b1' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // ===== Edit modal =====
    const editModal     = document.getElementById('modal-edit-transaction');
    const btnCloseEdit  = document.getElementById('btn-close-edit-modal');
    const btnCancelEdit = document.getElementById('btn-cancel-edit-modal');

    function openEditModal(tx) {
        document.getElementById('edit-sale-id').value     = tx.sale_id;
        document.getElementById('edit-tx-date').value     = tx.transaction_date;
        document.getElementById('edit-tx-time').value     = tx.transaction_time;
        document.getElementById('edit-tx-service').value  = tx.service_type;
        document.getElementById('edit-tx-loads').value    = tx.num_loads;
        document.getElementById('edit-tx-amount').value   = tx.amount_paid;
        document.getElementById('edit-tx-cash').value     = tx.cash_on_hand;
        document.getElementById('edit-tx-spray').value    = tx.fabric_spray;
        editModal.style.display = 'flex';
    }
    function closeEditModal() { editModal.style.display = 'none'; }

    btnCloseEdit.addEventListener('click', closeEditModal);
    btnCancelEdit.addEventListener('click', closeEditModal);
    editModal.addEventListener('click', e => { if (e.target === editModal) closeEditModal(); });
</script>
HTML;
require __DIR__ . '/includes/footer_app.php';
