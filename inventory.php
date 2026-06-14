<?php
/**
 * inventory.php
 * Takines Labada Hub — Inventory Management
 * Owner only — track and update laundry supply stock levels
 */
require_once __DIR__ . '/config.php';
require_role('owner');

// ------------------------------------------------------------
// HANDLE STOCK UPDATE (PUT via POST)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $inventoryId   = (int)($_POST['inventory_id'] ?? 0);
    $addQuantity   = (int)($_POST['add_quantity'] ?? 0);
    $dateRestocked = $_POST['date_restocked'] ?? date('Y-m-d');
    $remarks       = trim($_POST['remarks'] ?? '');

    $errors = [];
    if ($inventoryId <= 0) {
        $errors[] = 'Please select an item to update.';
    }
    if ($addQuantity < 1) {
        $errors[] = 'Quantity to add must be at least 1.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRestocked)) {
        $dateRestocked = date('Y-m-d');
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect('inventory.php');
    }

    $stmt = $pdo->prepare('UPDATE inventory SET quantity = quantity + ? WHERE inventory_id = ?');
    $stmt->execute([$addQuantity, $inventoryId]);

    $stmt = $pdo->prepare('SELECT item_name, unit FROM inventory WHERE inventory_id = ?');
    $stmt->execute([$inventoryId]);
    $item = $stmt->fetch();

    $logDescription = ($item->item_name ?? 'Item') . " (+{$addQuantity})";
    $stmt = $pdo->prepare('INSERT INTO inventory_logs (inventory_id, add_quantity, date_restocked, remarks) VALUES (?,?,?,?)');
    $stmt->execute([$inventoryId, $addQuantity, $dateRestocked, $remarks ?: null]);

    flash('success', 'Stock updated successfully.');
    redirect('inventory.php');
}

// ------------------------------------------------------------
// FETCH INVENTORY
// ------------------------------------------------------------
$stmt = $pdo->query('SELECT * FROM inventory ORDER BY item_name');
$rawItems = $stmt->fetchAll();

$inventoryItems = [];
foreach ($rawItems as $item) {
    $pct = $item->max_quantity > 0 ? min(100, round(($item->quantity / $item->max_quantity) * 100)) : 0;
    $ratio = $item->max_quantity > 0 ? ($item->quantity / $item->max_quantity) : 1;

    if ($ratio <= 0.2) {
        $status = 'critical';
    } elseif ($ratio <= 0.4) {
        $status = 'low';
    } else {
        $status = 'ok';
    }

    $item->pct    = $pct;
    $item->status = $status;
    $item->max    = $item->max_quantity; // alias for template convenience
    $inventoryItems[] = $item;
}

$lowStockAlertCount = count(array_filter($inventoryItems, fn($i) => $i->status === 'low' || $i->status === 'critical'));

// Recent updates
$stmt = $pdo->query("SELECT l.*, i.item_name, i.unit
                      FROM inventory_logs l
                      JOIN inventory i ON i.inventory_id = l.inventory_id
                      ORDER BY l.date_restocked DESC, l.log_id DESC
                      LIMIT 5");
$recentUpdates = $stmt->fetchAll();

$pageTitle = 'Inventory';
$activeNav = 'inventory';
require __DIR__ . '/includes/header_app.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <div class="breadcrumb">
            <a href="dashboard.php">Home</a>
            <span>&rsaquo;</span>
            <span class="current">Inventory</span>
        </div>
        <h1 class="page-title">Inventory Management</h1>
        <p class="page-subtitle">Track and update laundry supply stock levels</p>
    </div>
</div>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success" role="alert"><i class="ph-bold ph-check-circle"></i> <?= h($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
    <div class="alert alert-danger" role="alert"><i class="ph-bold ph-warning"></i> <?= h($msg) ?></div>
<?php endif; ?>

<!-- Low stock alert -->
<?php if ($lowStockAlertCount > 0): ?>
    <div class="alert alert-warning" role="alert">
        <i class="ph-bold ph-warning" aria-hidden="true"></i>
        <span><strong><?= $lowStockAlertCount ?> item<?= $lowStockAlertCount > 1 ? 's are' : ' is' ?> running low.</strong> Consider restocking soon.</span>
    </div>
<?php endif; ?>

<!-- Two-column layout: Stock List + Update Panel -->
<div style="display:grid; grid-template-columns:1fr 380px; gap:var(--spacing-lg); align-items:start;">

    <!-- LEFT — Supply Stock Levels -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Supply Stock Levels</span>
        </div>
        <div class="card-body" style="padding-top:var(--spacing-sm);">

            <?php foreach ($inventoryItems as $item): ?>
                <?php
                    $barClass = match ($item->status) {
                        'critical' => 'bar-red',
                        'low'      => 'bar-orange',
                        default    => $item->pct > 50 ? 'bar-green' : 'bar-blue',
                    };
                    $qtyClass = match ($item->status) {
                        'critical' => 'text-danger',
                        'low'      => 'text-warning',
                        default    => 'text-success',
                    };
                    $statusLabel = match ($item->status) {
                        'critical' => 'Critical!',
                        'low'      => 'Low stock!',
                        default    => 'In stock',
                    };
                ?>
                <div class="inv-item" id="inv-item-<?= (int)$item->inventory_id ?>" role="listitem">
                    <div class="inv-icon" aria-hidden="true"><?= h($item->icon) ?></div>
                    <div class="inv-info">
                        <div class="inv-name"><?= h($item->item_name) ?></div>
                        <div class="inv-unit"><?= h($item->unit) ?></div>
                        <div class="inv-bar" role="progressbar"
                             aria-valuenow="<?= (int)$item->quantity ?>"
                             aria-valuemax="<?= (int)$item->max ?>"
                             aria-label="<?= h($item->item_name) ?> stock level">
                            <div class="inv-bar-fill <?= $barClass ?>" style="width: <?= (int)$item->pct ?>%;"></div>
                        </div>
                    </div>
                    <div class="inv-qty">
                        <div class="qty-val <?= $qtyClass ?>"><?= (int)$item->quantity ?></div>
                        <div class="qty-label <?= $qtyClass ?>"><?= h($statusLabel) ?></div>
                    </div>
                    <button class="action-btn edit"
                            onclick="selectInventoryItem(<?= (int)$item->inventory_id ?>, <?= (int)$item->quantity ?>)"
                            aria-label="Update <?= h($item->item_name) ?>" title="Update stock">
                        <i class="ph-bold ph-pencil-simple"></i>
                    </button>
                </div>
            <?php endforeach; ?>

        </div>
    </div>

    <!-- RIGHT — Update Stock Panel -->
    <div class="card" style="position:sticky; top:calc(var(--topbar-height) + var(--spacing-lg));">
        <div class="card-header">
            <span class="card-title">Update Stock Quantity</span>
        </div>
        <div class="card-body">

            <form method="POST" action="inventory.php" id="form-update-stock" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="select-item">Select Item</label>
                    <select id="select-item" name="inventory_id" class="form-control" required aria-required="true" onchange="onItemSelect(this)">
                        <option value="">Choose an item&hellip;</option>
                        <?php foreach ($inventoryItems as $item): ?>
                            <option value="<?= (int)$item->inventory_id ?>" data-qty="<?= (int)$item->quantity ?>">
                                <?= h($item->item_name) ?> — <?= (int)$item->quantity ?> <?= h($item->unit) ?>s remaining
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="current-qty">Current Qty</label>
                        <input type="number" id="current-qty" name="current_qty" class="form-control"
                               readonly disabled placeholder="—" aria-readonly="true">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add-qty">Add Quantity</label>
                        <input type="number" id="add-qty" name="add_quantity" class="form-control"
                               placeholder="Enter amount" min="1" required aria-required="true">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="date-restocked">Date Restocked</label>
                    <input type="date" id="date-restocked" name="date_restocked" class="form-control"
                           value="<?= date('Y-m-d') ?>" required aria-required="true">
                </div>

                <div class="form-group">
                    <label class="form-label" for="remarks">Remarks (Optional)</label>
                    <input type="text" id="remarks" name="remarks" class="form-control"
                           placeholder="e.g. Purchased from Puregold" maxlength="255">
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="ph-bold ph-check" aria-hidden="true"></i>
                    Update Stock
                </button>

            </form>

            <!-- Recent Updates -->
            <div style="margin-top:var(--spacing-lg);">
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;
                            color:var(--color-text-muted); margin-bottom:var(--spacing-sm);">
                    Recent Updates
                </div>

                <?php if (empty($recentUpdates)): ?>
                    <p class="text-muted text-sm">No restock history yet.</p>
                <?php else: ?>
                    <?php foreach ($recentUpdates as $update): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center;
                                    padding: 8px 0; border-bottom: 1px solid var(--color-border-light);
                                    font-size:12px;">
                            <span style="font-weight:500;">
                                <?= h($update->item_name) ?> (+<?= (int)$update->add_quantity ?>)
                            </span>
                            <span class="text-muted"><?= date('M j, Y', strtotime($update->date_restocked)) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

</div><!-- /.two-column -->

<?php
$pageScripts = <<<'HTML'
<script>
    function selectInventoryItem(id, qty) {
        const select  = document.getElementById('select-item');
        const current = document.getElementById('current-qty');

        select.value = id;
        current.value = qty;
        current.removeAttribute('disabled');

        document.getElementById('form-update-stock').scrollIntoView({ behavior: 'smooth', block: 'start' });
        document.getElementById('add-qty').focus();
    }

    function onItemSelect(selectEl) {
        const opt = selectEl.options[selectEl.selectedIndex];
        const qty = opt.dataset.qty;
        const currentEl = document.getElementById('current-qty');
        if (qty !== undefined) {
            currentEl.value = qty;
            currentEl.removeAttribute('disabled');
        } else {
            currentEl.value = '';
        }
    }
</script>
HTML;
require __DIR__ . '/includes/footer_app.php';
