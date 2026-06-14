<?php
/**
 * reports_tax.php
 * Takines Labada Hub — Quarterly Percentage Tax (QPT) Report
 * 3% QPT on gross quarterly sales (fixed rate)
 */
require_once __DIR__ . '/config.php';
require_role('owner');

const QPT_RATE = 0.03;

$year = (int)($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

$currentQuarter = (int)ceil((int)date('n') / 3);

$quarters = [];
for ($q = 1; $q <= 4; $q++) {
    $startMonth = ($q - 1) * 3 + 1;
    $endMonth   = $q * 3;

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) AS total FROM sales
                            WHERE YEAR(transaction_date)=? AND MONTH(transaction_date) BETWEEN ? AND ?");
    $stmt->execute([$year, $startMonth, $endMonth]);
    $sales = (float)$stmt->fetch()->total;

    $quarters[] = [
        'q'     => $q,
        'label' => 'Q' . $q,
        'months' => date('M', mktime(0,0,0,$startMonth,1)) . ' &ndash; ' . date('M', mktime(0,0,0,$endMonth,1)),
        'sales' => $sales,
        'tax'   => $sales * QPT_RATE,
        'is_current' => ($q === $currentQuarter && $year === (int)date('Y')),
    ];
}

$yearTotalSales = array_sum(array_column($quarters, 'sales'));
$yearTotalTax   = array_sum(array_column($quarters, 'tax'));

$pageTitle = 'Tax Reports';
$activeNav = 'reports.tax';
require __DIR__ . '/includes/header_app.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <div class="breadcrumb">
            <a href="dashboard.php">Home</a>
            <span>&rsaquo;</span>
            <span class="current">Tax Reports</span>
        </div>
        <h1 class="page-title">Quarterly Percentage Tax (QPT)</h1>
        <p class="page-subtitle">3% QPT on gross quarterly sales — fixed rate</p>
    </div>
    <button class="btn btn-outline" onclick="window.print()">
        <i class="ph-bold ph-printer" aria-hidden="true"></i>
        Print Report
    </button>
</div>

<!-- Year Selector -->
<div style="display:flex; align-items:center; gap:var(--spacing-md); margin-bottom:var(--spacing-lg);">
    <form method="GET" action="reports_tax.php" style="display:flex; gap:8px; align-items:center;">
        <label for="report-year" class="form-label" style="margin-bottom:0; white-space:nowrap;">Select Year:</label>
        <select id="report-year" name="year" class="form-control" style="width:auto;" onchange="this.form.submit()">
            <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 4; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </form>
</div>

<!-- Summary cards -->
<div class="grid-2 mb-lg">
    <div class="stat-card blue">
        <div class="stat-card-label">Total Gross Sales (<?= $year ?>)</div>
        <div class="stat-card-value"><?= peso($yearTotalSales) ?></div>
        <div class="stat-card-sub">Sum of all 4 quarters</div>
    </div>
    <div class="stat-card teal">
        <div class="stat-card-label">Total QPT Due (<?= $year ?>)</div>
        <div class="stat-card-value"><?= peso($yearTotalTax) ?></div>
        <div class="stat-card-sub">3% of gross sales</div>
    </div>
</div>

<!-- Quarterly breakdown -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Quarterly Breakdown — <?= $year ?></span>
    </div>
    <div class="table-container" style="border:none; border-radius:0;">
        <table class="data-table" aria-label="Quarterly tax breakdown">
            <thead>
                <tr>
                    <th scope="col">Quarter</th>
                    <th scope="col">Period</th>
                    <th scope="col">Gross Sales</th>
                    <th scope="col">QPT Rate</th>
                    <th scope="col">QPT Due</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quarters as $q): ?>
                    <tr <?= $q['is_current'] ? 'style="background:var(--color-info-bg);"' : '' ?>>
                        <td>
                            <span class="badge badge-blue"><?= h($q['label']) ?></span>
                            <?php if ($q['is_current']): ?> <span class="text-muted text-sm">(current)</span><?php endif; ?>
                        </td>
                        <td><?= $q['months'] ?></td>
                        <td><?= peso($q['sales']) ?></td>
                        <td>3%</td>
                        <td style="font-weight:700; color:var(--color-primary);"><?= peso($q['tax']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="font-weight:700;">Annual Total</td>
                    <td style="font-weight:700;"><?= peso($yearTotalSales) ?></td>
                    <td></td>
                    <td style="font-weight:700; color:var(--color-primary);"><?= peso($yearTotalTax) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<p class="text-muted text-sm mt-md">
    Note: This report estimates the 3% Quarterly Percentage Tax (QPT) based on gross sales recorded in the system.
    Consult your accountant or the Bureau of Internal Revenue (BIR) for official filing requirements.
</p>

<?php
require __DIR__ . '/includes/footer_app.php';
