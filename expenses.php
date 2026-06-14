<?php
/**
 * expenses.php
 * Takines Labada Hub — Expense Records
 * Owner only — rental, salaries, electricity, gas, supplies
 */
require_once __DIR__ . '/config.php';
require_role('owner');

$allowedTypes = ['Rental', 'Salaries', 'Electricity', 'Gas Tank', 'Supplies'];

// ------------------------------------------------------------
// HANDLE FORM SUBMISSIONS (Add / Edit / Delete)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $method = $_POST['_method'] ?? 'POST';

    if ($method === 'DELETE') {
        $id = (int)($_POST['expense_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM expenses WHERE expense_id = ?');
        $stmt->execute([$id]);
        flash('success', 'Expense record deleted.');
        redirect('expenses.php');
    }

    $dateIncurred = $_POST['date_incurred'] ?? '';
    $expenseType  = $_POST['expense_type'] ?? '';
    $description  = trim($_POST['description'] ?? '');
    $amount       = (float)($_POST['amount'] ?? 0);
    $remarks      = trim($_POST['remarks'] ?? '');

    $errors = [];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateIncurred)) {
        $errors[] = 'A valid date incurred is required.';
    }
    if (!in_array($expenseType, $allowedTypes, true)) {
        $errors[] = 'Please select a valid expense type.';
    }
    if ($description === '') {
        $errors[] = 'Please enter a description for this expense.';
    }
    if ($amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect('expenses.php');
    }

    if ($method === 'PUT') {
        $id = (int)($_POST['expense_id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE expenses SET date_incurred=?, expense_type=?, description=?, amount=?, remarks=? WHERE expense_id=?');
        $stmt->execute([$dateIncurred, $expenseType, $description, $amount, $remarks ?: null, $id]);
        flash('success', 'Expense record updated.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO expenses (date_incurred, expense_type, description, amount, remarks) VALUES (?,?,?,?,?)');
        $stmt->execute([$dateIncurred, $expenseType, $description, $amount, $remarks ?: null]);
        flash('success', 'Expense logged successfully.');
    }

    redirect('expenses.php');
}

// ------------------------------------------------------------
// FILTERS
// ------------------------------------------------------------
$activeFilter = $_GET['category'] ?? 'All';
$categories   = array_merge(['All'], $allowedTypes);
if (!in_array($activeFilter, $categories, true)) {
    $activeFilter = 'All';
}

$where  = ['YEAR(date_incurred) = YEAR(CURDATE())', 'MONTH(date_incurred) = MONTH(CURDATE())'];
$params = [];
if ($activeFilter !== 'All') {
    $where[] = 'expense_type = ?';
    $params[] = $activeFilter;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT * FROM expenses $whereSql ORDER BY date_incurred DESC, expense_id DESC");
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// ------------------------------------------------------------
// SUMMARY CARDS
// ------------------------------------------------------------
$stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) AS total FROM expenses
                      WHERE YEAR(date_incurred)=YEAR(CURDATE()) AND MONTH(date_incurred)=MONTH(CURDATE())");
$monthlyExpenses = (float)$stmt->fetch()->total;

$stmt = $pdo->query("SELECT * FROM expenses
                      WHERE YEAR(date_incurred)=YEAR(CURDATE()) AND MONTH(date_incurred)=MONTH(CURDATE())
                      ORDER BY amount DESC LIMIT 1");
$largestExpense = $stmt->fetch();

$stmt = $pdo->query("SELECT COALESCE(SUM(amount_paid),0) AS total FROM sales
                      WHERE YEAR(transaction_date)=YEAR(CURDATE()) AND MONTH(transaction_date)=MONTH(CURDATE())");
$monthlyRevenue = (float)$stmt->fetch()->total;
$netIncome = $monthlyRevenue - $monthlyExpenses;

$pageTitle = 'Expenses';
$activeNav = 'expenses';
require __DIR__ . '/includes/header_app.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <div class="breadcrumb">
            <a href="dashboard.php">Home</a>
            <span>&rsaquo;</span>
            <span class="current">Expenses</span>
        </div>
        <h1 class="page-title">Expense Records</h1>
        <p class="page-subtitle">Track all business operating costs</p>
    </div>
    <button class="btn btn-primary" id="btn-log-expense" aria-haspopup="dialog">
        <i class="ph-bold ph-plus" aria-hidden="true"></i>
        + Log Expense
    </button>
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

<!-- EXPENSE SUMMARY CARDS — 3 columns -->
<div class="grid-3 mb-lg">
    <div class="stat-card orange">
        <div class="stat-card-label">This Month</div>
        <div class="stat-card-value"><?= peso($monthlyExpenses) ?></div>
        <div class="stat-card-sub"><?= date('F Y') ?></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-card-label">Largest Expense</div>
        <div class="stat-card-value"><?= peso($largestExpense->amount ?? 0) ?></div>
        <div class="stat-card-sub"><?= h($largestExpense->expense_type ?? '—') ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-label">Net Income</div>
        <div class="stat-card-value"><?= peso($netIncome) ?></div>
        <div class="stat-card-sub" style="color:var(--color-success); font-weight:600;">
            <?= peso($monthlyRevenue) ?> &ndash; <?= peso($monthlyExpenses) ?>
        </div>
    </div>
</div>

<!-- EXPENSE TABLE -->
<div class="card">

    <!-- Table Toolbar: Filter tabs -->
    <div style="padding: var(--spacing-md) var(--spacing-lg);
                border-bottom: 1px solid var(--color-border);
                display:flex; align-items:center; gap:var(--spacing-md); flex-wrap:wrap;">

        <div class="filter-tabs" role="group" aria-label="Expense category filter">
            <?php foreach ($categories as $cat): ?>
                <a href="?category=<?= urlencode($cat) ?>"
                   class="filter-tab <?= $activeFilter === $cat ? 'active' : '' ?>"
                   aria-pressed="<?= $activeFilter === $cat ? 'true' : 'false' ?>">
                    <?= h($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- Table -->
    <div class="table-container" style="border:none; border-radius:0;">
        <table class="data-table" aria-label="Expense records">
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Expense Type</th>
                    <th scope="col">Particulars</th>
                    <th scope="col">Amount</th>
                    <th scope="col">Remarks</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding: var(--spacing-lg); color:var(--color-text-muted);">
                            No expense records found for this filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    $typeColors = [
                        'Rental'      => 'orange',
                        'Salaries'    => 'blue',
                        'Electricity' => 'red',
                        'Gas Tank'    => 'teal',
                        'Supplies'    => 'green',
                    ];
                    ?>
                    <?php foreach ($expenses as $expense): ?>
                        <?php $tColor = $typeColors[$expense->expense_type] ?? 'gray'; ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($expense->date_incurred)) ?></td>
                            <td><span class="badge badge-<?= $tColor ?>"><?= h($expense->expense_type) ?></span></td>
                            <td><?= h($expense->description) ?></td>
                            <td><span style="color:var(--color-danger); font-weight:700;"><?= peso($expense->amount) ?></span></td>
                            <td class="text-muted"><?= h($expense->remarks ?: '—') ?></td>
                            <td>
                                <button class="action-btn edit"
                                        onclick='openEditExpense(<?= json_encode([
                                            "expense_id"    => $expense->expense_id,
                                            "date_incurred" => $expense->date_incurred,
                                            "expense_type"  => $expense->expense_type,
                                            "description"   => $expense->description,
                                            "amount"        => (float)$expense->amount,
                                            "remarks"       => $expense->remarks,
                                        ]) ?>)'
                                        aria-label="Edit expense" title="Edit">
                                    <i class="ph-bold ph-pencil-simple"></i>
                                </button>
                                <form method="POST" action="expenses.php" style="display:inline;"
                                      onsubmit="return confirm('Delete this expense record?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_method" value="DELETE">
                                    <input type="hidden" name="expense_id" value="<?= (int)$expense->expense_id ?>">
                                    <button type="submit" class="action-btn delete" aria-label="Delete expense" title="Delete">
                                        <i class="ph-bold ph-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Table Footer: Total -->
    <div style="padding: var(--spacing-md) var(--spacing-lg);
                border-top: 1px solid var(--color-border);
                display:flex; justify-content:space-between; align-items:center;">
        <span style="font-size:14px; font-weight:700;">
            Total Expenses (<?= date('F Y') ?>):
            <span style="color:var(--color-danger);"><?= peso($monthlyExpenses) ?></span>
        </span>
    </div>

</div><!-- /.card -->


<!-- LOG EXPENSE MODAL -->
<div class="modal-overlay" id="modal-log-expense" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="expense-modal-title">
    <div class="modal-dialog">

        <div class="modal-header">
            <div class="modal-title" id="expense-modal-title">
                <span aria-hidden="true">🧾</span>
                Log Expense
            </div>
            <button class="modal-close" id="btn-close-expense-modal" aria-label="Close">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>

        <form method="POST" action="expenses.php" id="form-log-expense" novalidate>
            <?= csrf_field() ?>

            <div class="modal-body">

                <div class="form-group">
                    <label class="form-label" for="exp-date">Date Incurred</label>
                    <input type="date" id="exp-date" name="date_incurred" class="form-control"
                           value="<?= date('Y-m-d') ?>" required aria-required="true">
                </div>

                <div class="form-group">
                    <label class="form-label" for="exp-type">Expense Type</label>
                    <select id="exp-type" name="expense_type" class="form-control" required aria-required="true">
                        <option value="">Select type&hellip;</option>
                        <?php foreach ($allowedTypes as $type): ?>
                            <option value="<?= h($type) ?>"><?= h($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="exp-desc">Particulars / Description</label>
                    <input type="text" id="exp-desc" name="description" class="form-control"
                           placeholder="e.g. Monthly shop rental — May 2026"
                           required aria-required="true" maxlength="500">
                </div>

                <div class="form-group">
                    <label class="form-label" for="exp-amount">Amount (₱)</label>
                    <input type="number" id="exp-amount" name="amount" class="form-control"
                           placeholder="0.00" min="0.01" step="0.01" required aria-required="true">
                </div>

                <div class="form-group">
                    <label class="form-label" for="exp-remarks">Remarks (Optional)</label>
                    <input type="text" id="exp-remarks" name="remarks" class="form-control"
                           placeholder="e.g. Paid to landlord" maxlength="255">
                </div>

            </div><!-- /.modal-body -->

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="btn-cancel-expense">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check" aria-hidden="true"></i>
                    Save Expense
                </button>
            </div>

        </form>
    </div>
</div>


<!-- EDIT EXPENSE MODAL -->
<div class="modal-overlay" id="modal-edit-expense" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="edit-expense-modal-title">
    <div class="modal-dialog">

        <div class="modal-header">
            <div class="modal-title" id="edit-expense-modal-title">
                <span aria-hidden="true">✏️</span>
                Edit Expense
            </div>
            <button class="modal-close" id="btn-close-edit-expense-modal" aria-label="Close">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>

        <form method="POST" action="expenses.php" id="form-edit-expense" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="PUT">
            <input type="hidden" name="expense_id" id="edit-expense-id">

            <div class="modal-body">

                <div class="form-group">
                    <label class="form-label" for="edit-exp-date">Date Incurred</label>
                    <input type="date" id="edit-exp-date" name="date_incurred" class="form-control" required aria-required="true">
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-exp-type">Expense Type</label>
                    <select id="edit-exp-type" name="expense_type" class="form-control" required aria-required="true">
                        <?php foreach ($allowedTypes as $type): ?>
                            <option value="<?= h($type) ?>"><?= h($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-exp-desc">Particulars / Description</label>
                    <input type="text" id="edit-exp-desc" name="description" class="form-control" required aria-required="true" maxlength="500">
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-exp-amount">Amount (₱)</label>
                    <input type="number" id="edit-exp-amount" name="amount" class="form-control" min="0.01" step="0.01" required aria-required="true">
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-exp-remarks">Remarks (Optional)</label>
                    <input type="text" id="edit-exp-remarks" name="remarks" class="form-control" maxlength="255">
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="btn-cancel-edit-expense">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check" aria-hidden="true"></i>
                    Update Expense
                </button>
            </div>

        </form>
    </div>
</div>

<?php
$pageScripts = <<<'HTML'
<script>
    const expModal = document.getElementById('modal-log-expense');
    document.getElementById('btn-log-expense').addEventListener('click', () => { expModal.style.display = 'flex'; });
    document.getElementById('btn-close-expense-modal').addEventListener('click', () => { expModal.style.display = 'none'; });
    document.getElementById('btn-cancel-expense').addEventListener('click', () => { expModal.style.display = 'none'; });
    expModal.addEventListener('click', e => { if (e.target === expModal) expModal.style.display = 'none'; });

    if (window.location.hash === '#add') expModal.style.display = 'flex';

    const editExpModal = document.getElementById('modal-edit-expense');
    document.getElementById('btn-close-edit-expense-modal').addEventListener('click', () => { editExpModal.style.display = 'none'; });
    document.getElementById('btn-cancel-edit-expense').addEventListener('click', () => { editExpModal.style.display = 'none'; });
    editExpModal.addEventListener('click', e => { if (e.target === editExpModal) editExpModal.style.display = 'none'; });

    function openEditExpense(exp) {
        document.getElementById('edit-expense-id').value   = exp.expense_id;
        document.getElementById('edit-exp-date').value     = exp.date_incurred;
        document.getElementById('edit-exp-type').value     = exp.expense_type;
        document.getElementById('edit-exp-desc').value     = exp.description;
        document.getElementById('edit-exp-amount').value   = exp.amount;
        document.getElementById('edit-exp-remarks').value  = exp.remarks || '';
        editExpModal.style.display = 'flex';
    }
</script>
HTML;
require __DIR__ . '/includes/footer_app.php';
