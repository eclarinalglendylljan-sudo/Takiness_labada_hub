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

// ------------------------------------------------------------
// REVENUE VS EXPENSES — last 6 months comparison
// ------------------------------------------------------------
$comparisonMonths = [];
for ($i = 5; $i >= 0; $i--) {
    $ts    = strtotime("first day of -$i month");
    $yr    = (int)date('Y', $ts);
    $mo    = (int)date('n', $ts);
    $label = date('M Y', $ts);
    $comparisonMonths[] = ['year' => $yr, 'month' => $mo, 'label' => $label];
}

$revenueByMonth  = [];
$expensesByMonth = [];
foreach ($comparisonMonths as $cm) {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount_paid),0) AS total FROM sales
         WHERE YEAR(transaction_date) = ? AND MONTH(transaction_date) = ?"
    );
    $stmt->execute([$cm['year'], $cm['month']]);
    $revenueByMonth[] = (float)$stmt->fetch()->total;

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) AS total FROM expenses
         WHERE YEAR(date_incurred) = ? AND MONTH(date_incurred) = ?"
    );
    $stmt->execute([$cm['year'], $cm['month']]);
    $expensesByMonth[] = (float)$stmt->fetch()->total;
}

$comparisonMax = max(array_merge($revenueByMonth, $expensesByMonth, [1]));

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

<!-- REVENUE VS EXPENSES COMPARISON CHART — full width -->
<?php
    // Pre-compute totals for the summary table footer
    $totalRev = array_sum($revenueByMonth);
    $totalExp = array_sum($expensesByMonth);
    $totalNet = $totalRev - $totalExp;

    // Build PHP arrays → JSON for Chart.js
    $chartLabels   = array_column($comparisonMonths, 'label');
    $netByMonth    = [];
    foreach ($comparisonMonths as $idx => $cm) {
        $netByMonth[] = round($revenueByMonth[$idx] - $expensesByMonth[$idx], 2);
    }
    $chartLabelsJson   = json_encode($chartLabels);
    $chartRevenueJson  = json_encode(array_map(fn($v) => round($v, 2), $revenueByMonth));
    $chartExpensesJson = json_encode(array_map(fn($v) => round($v, 2), $expensesByMonth));
    $chartNetJson      = json_encode($netByMonth);
?>
<div class="card mb-lg">
    <div class="card-header">
        <span class="card-title">Revenue vs Expenses</span>
        <span style="font-size:12px; color:var(--color-text-muted);">Last 6 Months</span>
    </div>
    <div class="card-body">

        <!-- Chart.js Canvas — reliable cross-browser rendering -->
        <div style="position:relative; width:100%; height:260px;">
            <canvas id="revenueExpensesChart" aria-label="Revenue vs Expenses bar chart for the last 6 months" role="img"></canvas>
        </div>

        <!-- Amounts summary table -->
        <div style="margin-top:var(--spacing-lg); overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:12px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--color-border);">
                        <th style="text-align:left; padding:6px 8px; color:var(--color-text-muted); font-weight:700;">Month</th>
                        <th style="text-align:right; padding:6px 8px; color:#3b82f6; font-weight:700;">Revenue</th>
                        <th style="text-align:right; padding:6px 8px; color:#ef4444; font-weight:700;">Expenses</th>
                        <th style="text-align:right; padding:6px 8px; font-weight:700;">Net Income</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comparisonMonths as $idx => $cm):
                        $rev = $revenueByMonth[$idx];
                        $exp = $expensesByMonth[$idx];
                        $net = $rev - $exp;
                    ?>
                    <tr style="border-bottom:1px solid var(--color-border);">
                        <td style="padding:7px 8px; font-weight:600; color:var(--color-text-secondary);"><?= h($cm['label']) ?></td>
                        <td style="padding:7px 8px; text-align:right; color:#3b82f6; font-weight:600;"><?= peso($rev) ?></td>
                        <td style="padding:7px 8px; text-align:right; color:#ef4444; font-weight:600;"><?= peso($exp) ?></td>
                        <td style="padding:7px 8px; text-align:right; font-weight:700;
                            color:<?= $net >= 0 ? '#22c55e' : '#ef4444' ?>;">
                            <?= $net >= 0 ? '' : '−' ?><?= peso(abs($net)) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--color-surface-alt, rgba(0,0,0,.03)); border-top:2px solid var(--color-border);">
                        <td style="padding:8px 8px; font-weight:800; font-size:12px;">6-Month Total</td>
                        <td style="padding:8px 8px; text-align:right; color:#3b82f6; font-weight:800;"><?= peso($totalRev) ?></td>
                        <td style="padding:8px 8px; text-align:right; color:#ef4444; font-weight:800;"><?= peso($totalExp) ?></td>
                        <td style="padding:8px 8px; text-align:right; font-weight:800;
                            color:<?= $totalNet >= 0 ? '#22c55e' : '#ef4444' ?>;">
                            <?= $totalNet >= 0 ? '' : '−' ?><?= peso(abs($totalNet)) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

    </div>
</div>

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
// Pass PHP data to JS safely
$jsLabels   = json_encode($chartLabels);
$jsRevenue  = json_encode(array_map(fn($v) => round($v, 2), $revenueByMonth));
$jsExpenses = json_encode(array_map(fn($v) => round($v, 2), $expensesByMonth));
$jsNet      = json_encode($netByMonth);

$pageScripts = <<<HTML
<!-- Chart.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    // ── Revenue vs Expenses grouped bar + net income line ──────────────
    var ctx = document.getElementById('revenueExpensesChart');
    if (ctx) {
        new Chart(ctx, {
            data: {
                labels: {$jsLabels},
                datasets: [
                    {
                        type: 'bar',
                        label: 'Revenue',
                        data: {$jsRevenue},
                        backgroundColor: 'rgba(59,130,246,0.85)',
                        borderColor: '#3b82f6',
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false,
                        order: 2
                    },
                    {
                        type: 'bar',
                        label: 'Expenses',
                        data: {$jsExpenses},
                        backgroundColor: 'rgba(239,68,68,0.80)',
                        borderColor: '#ef4444',
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false,
                        order: 3
                    },
                    {
                        type: 'line',
                        label: 'Net Income',
                        data: {$jsNet},
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34,197,94,0.12)',
                        borderWidth: 2.5,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#22c55e',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        fill: false,
                        tension: 0.35,
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'rectRounded',
                            boxWidth: 10,
                            boxHeight: 10,
                            padding: 16,
                            font: { size: 12, weight: '600' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var val = ctx.parsed.y;
                                var sign = (ctx.dataset.label === 'Net Income' && val < 0) ? '−₱' : '₱';
                                return ' ' + ctx.dataset.label + ': ' + sign + Math.abs(val).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11, weight: '600' } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.06)' },
                        ticks: {
                            font: { size: 11 },
                            callback: function(val) {
                                if (val >= 1000) return '₱' + (val/1000).toFixed(1) + 'k';
                                return '₱' + val;
                            }
                        }
                    }
                }
            }
        });
    }

    // ── Period-toggle buttons (visual only for now) ──────────────────────
    document.querySelectorAll('.period-btn').forEach(function(btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.period-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
        });
    });
})();
</script>
HTML;
require __DIR__ . '/includes/footer_app.php';