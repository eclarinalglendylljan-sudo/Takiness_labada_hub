<?php
/**
 * reports_income.php
 * Takines Labada Hub — Income Summary & Cash Flow
 * Computed on-request (no DB write)
 */
require_once __DIR__ . '/config.php';
require_role('owner');

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
[$year, $mon] = array_map('intval', explode('-', $month));

// Total sales for selected month
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) AS total FROM sales
                        WHERE YEAR(transaction_date)=? AND MONTH(transaction_date)=?");
$stmt->execute([$year, $mon]);
$totalSales = (float)$stmt->fetch()->total;

// Total expenses for selected month
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM expenses
                        WHERE YEAR(date_incurred)=? AND MONTH(date_incurred)=?");
$stmt->execute([$year, $mon]);
$totalExpenses = (float)$stmt->fetch()->total;

$netIncome = $totalSales - $totalExpenses;

// Cash on hand (latest recorded value within selected month, else overall latest)
$stmt = $pdo->prepare("SELECT cash_on_hand FROM sales
                        WHERE YEAR(transaction_date)=? AND MONTH(transaction_date)=?
                        ORDER BY transaction_date DESC, transaction_time DESC, sale_id DESC LIMIT 1");
$stmt->execute([$year, $mon]);
$cashRow = $stmt->fetch();
$cashOnHand = $cashRow ? (float)$cashRow->cash_on_hand : 0;

// Expense breakdown by type for selected month
$stmt = $pdo->prepare("SELECT expense_type AS type, COALESCE(SUM(amount),0) AS amount
                        FROM expenses
                        WHERE YEAR(date_incurred)=? AND MONTH(date_incurred)=?
                        GROUP BY expense_type
                        ORDER BY amount DESC");
$stmt->execute([$year, $mon]);
$expenseBreakdown = $stmt->fetchAll();

// Monthly trend — net income for the selected month and the 4 months before it
$monthlyTrend = [];
$trendValues  = [];
for ($i = 4; $i >= 0; $i--) {
    $ts = mktime(0, 0, 0, $mon - $i, 1, $year);
    $y  = (int)date('Y', $ts);
    $m  = (int)date('n', $ts);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) AS total FROM sales WHERE YEAR(transaction_date)=? AND MONTH(transaction_date)=?");
    $stmt->execute([$y, $m]);
    $rev = (float)$stmt->fetch()->total;

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE YEAR(date_incurred)=? AND MONTH(date_incurred)=?");
    $stmt->execute([$y, $m]);
    $exp = (float)$stmt->fetch()->total;

    $net = $rev - $exp;
    $monthlyTrend[] = [
        'label'   => date('M', $ts),
        'net'     => $net,
        'current' => ($y === $year && $m === $mon),
    ];
    $trendValues[] = $net;
}
$maxTrend = max(array_merge($trendValues, [1]));
foreach ($monthlyTrend as &$t) {
    $t['pct'] = $maxTrend != 0 ? max(0, round(($t['net'] / $maxTrend) * 100)) : 0;
}
unset($t);

$pageTitle = 'Income & Reports';
$activeNav = 'reports.income';
require __DIR__ . '/includes/header_app.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <div class="breadcrumb">
            <a href="dashboard.php">Home</a>
            <span>&rsaquo;</span>
            <span class="current">Income &amp; Reports</span>
        </div>
        <h1 class="page-title">Income Summary &amp; Cash Flow</h1>
        <p class="page-subtitle">Monthly financial overview and reports</p>
    </div>
    <div style="display:flex; gap:8px;">
        <button class="btn btn-outline" onclick="window.print()">
            <i class="ph-bold ph-printer" aria-hidden="true"></i>
            Print Report
        </button>
    </div>
</div>

<!-- Month Selector -->
<div style="display:flex; align-items:center; gap:var(--spacing-md); margin-bottom:var(--spacing-lg);">
    <form method="GET" action="reports_income.php" style="display:flex; gap:8px; align-items:center;">
        <label for="report-month" class="form-label" style="margin-bottom:0; white-space:nowrap;">
            Select Month:
        </label>
        <input type="month" id="report-month" name="month" class="form-control" style="width:auto;"
               value="<?= h($month) ?>" onchange="this.form.submit()">
    </form>
    <span class="text-muted text-sm">
        Showing data for <strong><?= date('F Y', mktime(0, 0, 0, $mon, 1, $year)) ?></strong>
    </span>
</div>

<!-- MAIN REPORT — 2 column layout -->
<div class="grid-2">

    <!-- Monthly Income Summary -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Monthly Income Summary — <?= date('F Y', mktime(0, 0, 0, $mon, 1, $year)) ?></span>
        </div>
        <div class="card-body">

            <div style="display:flex; justify-content:space-between; align-items:center;
                        padding: 14px 0; border-bottom: 1px solid var(--color-border-light);">
                <span style="font-size:14px; color:var(--color-text-secondary);">Total Sales Revenue</span>
                <span style="font-size:16px; font-weight:700; color:var(--color-success);">
                    + <?= peso($totalSales) ?>
                </span>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center;
                        padding: 14px 0; border-bottom: 1px solid var(--color-border-light);">
                <span style="font-size:14px; color:var(--color-text-secondary);">Total Expenses</span>
                <span style="font-size:16px; font-weight:700; color:var(--color-danger);">
                    &ndash; <?= peso($totalExpenses) ?>
                </span>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center;
                        padding: 16px 0; border-bottom: 2px solid var(--color-border);
                        background:var(--color-success-bg); margin:0 calc(var(--spacing-lg) * -1); padding-left:var(--spacing-lg); padding-right:var(--spacing-lg);">
                <span style="font-size:15px; font-weight:700;">Net Income</span>
                <span style="font-family:var(--font-primary); font-size:22px; font-weight:800; color:var(--color-success);">
                    <?= peso($netIncome) ?>
                </span>
            </div>

            <div style="padding: 14px 0; border-bottom: 1px solid var(--color-border-light);">
                <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px;
                            color:var(--color-text-muted); margin-bottom:6px;">
                    Cash on Hand (last verified)
                </div>
                <div style="font-family:var(--font-primary); font-size:20px; font-weight:800;">
                    <?= peso($cashOnHand) ?>
                </div>
            </div>

            <div style="margin-top:var(--spacing-md);">
                <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px;
                            color:var(--color-text-muted); margin-bottom:var(--spacing-sm);">
                    Expense Breakdown
                </div>
                <?php if (empty($expenseBreakdown)): ?>
                    <p class="text-muted text-sm">No expenses recorded for this month.</p>
                <?php else: ?>
                    <?php foreach ($expenseBreakdown as $b): ?>
                        <div style="display:flex; justify-content:space-between;
                                    padding: 8px 0; border-bottom: 1px solid var(--color-border-light);
                                    font-size:13px;">
                            <span><?= h($b->type) ?></span>
                            <span style="font-weight:600;"><?= peso($b->amount) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Cash Flow -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Cash Flow</span>
        </div>
        <div class="card-body">

            <div style="background:var(--color-info-bg); border-radius:var(--radius-md);
                        padding: var(--spacing-md); margin-bottom:var(--spacing-lg); text-align:center;">
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;
                            color:var(--color-info); margin-bottom:6px;">
                    Net (<?= date('M', mktime(0, 0, 0, $mon, 1, $year)) ?>)
                </div>
                <div style="font-family:var(--font-primary); font-size:26px; font-weight:800; color:var(--color-text-primary);">
                    <?= peso($netIncome) ?>
                </div>
            </div>

            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px;
                        color:var(--color-text-muted); margin-bottom:var(--spacing-md);">
                Monthly Trend
            </div>

            <div class="chart-area" style="height:140px;" role="img" aria-label="Monthly cash flow trend">
                <?php foreach ($monthlyTrend as $t): ?>
                    <div class="chart-bar-wrap">
                        <div class="chart-bar <?= $t['current'] ? 'current' : '' ?>"
                             style="height:<?= max($t['pct'], 3) ?>%;"
                             title="<?= h($t['label']) ?>: <?= peso($t['net']) ?>"></div>
                        <span class="chart-bar-label"><?= h($t['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

</div><!-- /.grid-2 -->

<?php
require __DIR__ . '/includes/footer_app.php';
