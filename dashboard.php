<?php
/**
 * dashboard.php
 * Takines Labada Hub — Owner Dashboard
 * Shows: today's sales, monthly revenue, net income, low stock
 */
require_once __DIR__ . '/config.php';
require_role('owner');

$user          = current_user();
$ownerFirstName = explode(' ', $user->name)[0];

$hour = (int)date('G');
$greeting = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');

// ------------------------------------------------------------
// STAT CARDS
// ------------------------------------------------------------

// Today's sales
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) AS total FROM sales WHERE transaction_date = CURDATE()");
$stmt->execute();
$todaysSales = (float)$stmt->fetch()->total;

// Yesterday's sales (for trend %)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) AS total FROM sales WHERE transaction_date = CURDATE() - INTERVAL 1 DAY");
$stmt->execute();
$yesterdaySales = (float)$stmt->fetch()->total;
$salesTrendPct = $yesterdaySales > 0
    ? round((($todaysSales - $yesterdaySales) / $yesterdaySales) * 100, 1)
    : ($todaysSales > 0 ? 100 : 0);

// This month's revenue
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) AS total FROM sales
                        WHERE YEAR(transaction_date) = YEAR(CURDATE()) AND MONTH(transaction_date) = MONTH(CURDATE())");
$stmt->execute();
$monthlyRevenue = (float)$stmt->fetch()->total;

// Last month's revenue (for trend %)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) AS total FROM sales
                        WHERE YEAR(transaction_date) = YEAR(CURDATE() - INTERVAL 1 MONTH)
                          AND MONTH(transaction_date) = MONTH(CURDATE() - INTERVAL 1 MONTH)");
$stmt->execute();
$lastMonthRevenue = (float)$stmt->fetch()->total;
$revenueTrendPct = $lastMonthRevenue > 0
    ? round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
    : ($monthlyRevenue > 0 ? 100 : 0);

// This month's expenses
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM expenses
                        WHERE YEAR(date_incurred) = YEAR(CURDATE()) AND MONTH(date_incurred) = MONTH(CURDATE())");
$stmt->execute();
$monthlyExpenses = (float)$stmt->fetch()->total;

$netIncomeMtd = $monthlyRevenue - $monthlyExpenses;

// Low stock items (quantity <= 40% of max => low/critical)
$stmt = $pdo->query("SELECT *,
                        CASE
                          WHEN max_quantity = 0 THEN 0
                          ELSE ROUND((quantity / max_quantity) * 100)
                        END AS pct
                      FROM inventory
                      ORDER BY item_name");
$allInventory = $stmt->fetchAll();

$lowStockItems = array_values(array_filter($allInventory, function ($item) {
    return $item->max_quantity > 0 && ($item->quantity / $item->max_quantity) <= 0.4;
}));
$lowStockCount = count($lowStockItems);

// ------------------------------------------------------------
// WEEKLY SALES CHART (Mon - Sun of current week)
// ------------------------------------------------------------
$weekStart = date('Y-m-d', strtotime('monday this week'));
$stmt = $pdo->prepare("SELECT transaction_date, SUM(amount_paid) AS total
                        FROM sales
                        WHERE transaction_date BETWEEN ? AND DATE_ADD(?, INTERVAL 6 DAY)
                        GROUP BY transaction_date");
$stmt->execute([$weekStart, $weekStart]);
$rows = $stmt->fetchAll();

$byDate = [];
foreach ($rows as $r) {
    $byDate[$r->transaction_date] = (float)$r->total;
}

$dayLabels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$maxAmount = max(array_merge($byDate, [1])); // avoid div by zero
$today = date('Y-m-d');
$weeklyChartData = [];
foreach ($dayLabels as $i => $label) {
    $date = date('Y-m-d', strtotime($weekStart . " +$i day"));
    $amount = $byDate[$date] ?? 0;
    $weeklyChartData[] = [
        'day'     => $label,
        'amount'  => $amount,
        'pct'     => $maxAmount > 0 ? round(($amount / $maxAmount) * 100) : 0,
        'current' => $date === $today,
    ];
}

// ------------------------------------------------------------
// QPT SUMMARY — 3% quarterly percentage tax
// ------------------------------------------------------------
$currentMonth   = (int)date('n');
$currentQuarter = (int)ceil($currentMonth / 3);
$quarterStartMonth = ($currentQuarter - 1) * 3 + 1;
$quarterEndMonth   = $currentQuarter * 3;

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) AS total FROM sales
                        WHERE YEAR(transaction_date) = YEAR(CURDATE())
                          AND MONTH(transaction_date) BETWEEN ? AND ?");
$stmt->execute([$quarterStartMonth, $quarterEndMonth]);
$quarterlyTotal = (float)$stmt->fetch()->total;
$qptAmount = $quarterlyTotal * 0.03;

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/includes/header_app.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <div class="breadcrumb">
            Home
            <span>&rsaquo;</span>
            <span class="current">Dashboard</span>
        </div>
        <h1 class="page-title">
            Good <?= h($greeting) ?>, <?= h($ownerFirstName) ?>! 👋
        </h1>
        <p class="page-subtitle">
            <?= date('l, F j, Y') ?> — Today's business at a glance
        </p>
    </div>
    <a href="sales.php#add" class="btn btn-primary">
        <i class="ph-bold ph-plus" aria-hidden="true"></i>
        + New Transaction
    </a>
</div>

<!-- INVENTORY ALERT BANNER -->
<?php if (count($lowStockItems) > 0): ?>
    <div class="alert alert-info" role="alert">
        <i class="ph-bold ph-info" aria-hidden="true"></i>
        <span>
            📦 Inventory alert:
            <?php foreach ($lowStockItems as $i => $item): ?>
                <strong><?= h($item->item_name) ?></strong> is running low
                (<?= (int)$item->quantity ?> <?= h($item->unit) ?> remaining)<?= $i < count($lowStockItems) - 1 ? ',' : '.' ?>
            <?php endforeach; ?>
        </span>
    </div>
<?php endif; ?>

<!-- STAT CARDS — 4-column grid -->
<div class="grid-4 mb-lg">

    <div class="stat-card green" aria-label="Today's sales">
        <div class="stat-card-label">Today's Sales</div>
        <div class="stat-card-value"><?= peso($todaysSales) ?></div>
        <div class="stat-card-trend <?= $salesTrendPct >= 0 ? 'trend-up' : 'trend-down' ?>">
            <i class="ph-bold ph-trend-<?= $salesTrendPct >= 0 ? 'up' : 'down' ?>" aria-hidden="true"></i>
            <?= h(abs($salesTrendPct)) ?>% vs yesterday
        </div>
        <div class="stat-card-icon">💰</div>
    </div>

    <div class="stat-card blue" aria-label="Monthly revenue">
        <div class="stat-card-label">Monthly Revenue</div>
        <div class="stat-card-value"><?= peso($monthlyRevenue) ?></div>
        <div class="stat-card-trend <?= $revenueTrendPct >= 0 ? 'trend-up' : 'trend-down' ?>">
            <i class="ph-bold ph-trend-<?= $revenueTrendPct >= 0 ? 'up' : 'down' ?>" aria-hidden="true"></i>
            <?= h(abs($revenueTrendPct)) ?>% vs last month
        </div>
        <div class="stat-card-icon">📊</div>
    </div>

    <div class="stat-card teal" aria-label="Net income month to date">
        <div class="stat-card-label">Net Income (MTD)</div>
        <div class="stat-card-value"><?= peso($netIncomeMtd) ?></div>
        <div class="stat-card-sub">After expenses</div>
        <div class="stat-card-icon">📈</div>
    </div>

    <div class="stat-card orange" aria-label="Low stock items">
        <div class="stat-card-label">Low Stock Items</div>
        <div class="stat-card-value" style="color: var(--color-warning);"><?= (int)$lowStockCount ?></div>
        <div class="stat-card-sub">Needs restocking</div>
        <div class="stat-card-icon">⚠️</div>
    </div>

</div><!-- /.grid-4 -->

<!-- BOTTOM ROW — Chart + Quick Access + QPT Summary -->
<div style="display: grid; grid-template-columns: 1fr 320px; gap: var(--spacing-lg);">

    <!-- Weekly Sales Chart -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Weekly Sales Overview</span>
            <div class="period-toggle" role="group" aria-label="Chart period">
                <button class="period-btn active" data-period="week">Week</button>
                <button class="period-btn" data-period="month">Month</button>
                <button class="period-btn" data-period="quarter">Quarter</button>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-area" role="img" aria-label="Weekly sales bar chart">
                <?php foreach ($weeklyChartData as $bar): ?>
                    <div class="chart-bar-wrap">
                        <div class="chart-bar <?= $bar['current'] ? 'current' : '' ?>"
                             style="height: <?= max($bar['pct'], 3) ?>%;"
                             title="<?= h($bar['day']) ?>: <?= peso($bar['amount']) ?>"
                             aria-label="<?= h($bar['day']) ?>: <?= peso($bar['amount']) ?>">
                        </div>
                        <span class="chart-bar-label"><?= h($bar['day']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div style="display:flex; flex-direction:column; gap: var(--spacing-lg);">

        <!-- Quick Access -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Quick Access</span>
            </div>
            <div class="card-body" style="padding-top: var(--spacing-md);">
                <div class="quick-access-grid">
                    <a href="sales.php#add" class="quick-access-btn" aria-label="Add a new sale">
                        <span class="qa-icon">💰</span>
                        <span class="qa-label">Add Sale</span>
                    </a>
                    <a href="inventory.php" class="quick-access-btn" aria-label="View inventory">
                        <span class="qa-icon">📦</span>
                        <span class="qa-label">Inventory</span>
                    </a>
                    <a href="expenses.php#add" class="quick-access-btn" aria-label="Log an expense">
                        <span class="qa-icon">🧾</span>
                        <span class="qa-label">Add Expense</span>
                    </a>
                    <a href="reports_income.php" class="quick-access-btn" aria-label="View reports">
                        <span class="qa-icon">📊</span>
                        <span class="qa-label">Reports</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- QPT Summary — 3% QPT on quarterly sales -->
        <div class="card">
            <div class="card-header">
                <span class="card-title" style="font-size:13px;">
                    QPT Summary (Q<?= (int)$currentQuarter ?> <?= date('Y') ?>)
                </span>
            </div>
            <div class="card-body">
                <div style="background:var(--color-info-bg); border-radius:var(--radius-md); padding: var(--spacing-md);">
                    <div style="font-size:11px; font-weight:700; color:var(--color-info); margin-bottom:6px;">
                        3% Quarterly Tax Due
                    </div>
                    <div style="font-family:var(--font-primary); font-size:22px; font-weight:800; color:var(--color-text-primary); letter-spacing:-0.5px;">
                        <?= peso($qptAmount) ?>
                    </div>
                    <div style="font-size:11px; color:var(--color-text-muted); margin-top:4px;">
                        On <?= peso($quarterlyTotal) ?> total sales
                    </div>
                </div>
                <a href="reports_tax.php" class="btn btn-outline btn-block mt-md" style="font-size:12px;">
                    View Full Tax Report
                </a>
            </div>
        </div>

    </div><!-- /.right-column -->

</div><!-- /.bottom-row -->

<?php
$pageScripts = <<<'HTML'
<script>
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });
</script>
HTML;
require __DIR__ . '/includes/footer_app.php';
