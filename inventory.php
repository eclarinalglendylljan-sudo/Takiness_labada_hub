<?php
/**
 * inventory.php
 * Takines Labada Hub — Inventory Management
 * Owner only — full CRUD: create, restock, edit, and delete inventory items
 */
require_once __DIR__ . '/config.php';
require_role('owner');

// ============================================================
// HELPER — JSON response for AJAX / fetch calls
// ============================================================
function json_response(bool $ok, string $message, array $extra = []): never
{
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'message' => $message, ...$extra]);
    exit;
}

// ============================================================
// POST ROUTER  ($_POST['_action'] drives every mutation)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['_action'] ?? 'restock';

    // ----------------------------------------------------------
    // CREATE — add a brand-new inventory item
    // ----------------------------------------------------------
    if ($action === 'create') {
        $itemName    = trim($_POST['item_name']    ?? '');
        $unit        = trim($_POST['unit']         ?? '');
        $icon        = trim($_POST['icon']         ?? '📦');
        $quantity    = (int)($_POST['quantity']    ?? 0);
        $maxQuantity = (int)($_POST['max_quantity'] ?? 0);

        $errors = [];
        if ($itemName === '')    $errors[] = 'Item name is required.';
        if ($unit === '')        $errors[] = 'Unit is required.';
        if ($quantity < 0)       $errors[] = 'Initial quantity cannot be negative.';
        if ($maxQuantity < 1)    $errors[] = 'Max quantity must be at least 1.';
        if ($maxQuantity < $quantity) $errors[] = 'Max quantity must be ≥ initial quantity.';

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('inventory.php');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO inventory (item_name, unit, icon, quantity, max_quantity)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$itemName, $unit, $icon, $quantity, $maxQuantity]);

        flash('success', '"' . $itemName . '" added to inventory.');
        redirect('inventory.php');
    }

    // ----------------------------------------------------------
    // RESTOCK — add stock to an existing item (original behaviour)
    // ----------------------------------------------------------
    if ($action === 'restock') {
        $inventoryId   = (int)($_POST['inventory_id']  ?? 0);
        $addQuantity   = (int)($_POST['add_quantity']   ?? 0);
        $dateRestocked = $_POST['date_restocked'] ?? date('Y-m-d');
        $remarks       = trim($_POST['remarks']   ?? '');

        $errors = [];
        if ($inventoryId <= 0) $errors[] = 'Please select an item to update.';
        if ($addQuantity < 1)  $errors[] = 'Quantity to add must be at least 1.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRestocked)) {
            $dateRestocked = date('Y-m-d');
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('inventory.php');
        }

        $stmt = $pdo->prepare(
            'UPDATE inventory SET quantity = quantity + ? WHERE inventory_id = ?'
        );
        $stmt->execute([$addQuantity, $inventoryId]);

        $stmt = $pdo->prepare('SELECT item_name FROM inventory WHERE inventory_id = ?');
        $stmt->execute([$inventoryId]);
        $item = $stmt->fetch();

        $stmt = $pdo->prepare(
            'INSERT INTO inventory_logs (inventory_id, add_quantity, date_restocked, remarks)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$inventoryId, $addQuantity, $dateRestocked, $remarks ?: null]);

        flash('success', 'Stock updated successfully.');
        redirect('inventory.php');
    }

    // ----------------------------------------------------------
    // EDIT — update item details (name, unit, icon, max_quantity)
    // ----------------------------------------------------------
    if ($action === 'edit') {
        $inventoryId = (int)($_POST['inventory_id']  ?? 0);
        $itemName    = trim($_POST['item_name']       ?? '');
        $unit        = trim($_POST['unit']            ?? '');
        $icon        = trim($_POST['icon']            ?? '📦');
        $maxQuantity = (int)($_POST['max_quantity']   ?? 0);

        $errors = [];
        if ($inventoryId <= 0) $errors[] = 'Invalid item.';
        if ($itemName === '')   $errors[] = 'Item name is required.';
        if ($unit === '')       $errors[] = 'Unit is required.';
        if ($maxQuantity < 1)  $errors[] = 'Max quantity must be at least 1.';

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('inventory.php');
        }

        $stmt = $pdo->prepare(
            'UPDATE inventory
             SET item_name = ?, unit = ?, icon = ?, max_quantity = ?
             WHERE inventory_id = ?'
        );
        $stmt->execute([$itemName, $unit, $icon, $maxQuantity, $inventoryId]);

        flash('success', '"' . $itemName . '" updated.');
        redirect('inventory.php');
    }

    // ----------------------------------------------------------
    // DELETE — remove an item and its logs
    // ----------------------------------------------------------
    if ($action === 'delete') {
        $inventoryId = (int)($_POST['inventory_id'] ?? 0);

        if ($inventoryId <= 0) {
            flash('error', 'Invalid item.');
            redirect('inventory.php');
        }

        $stmt = $pdo->prepare('SELECT item_name FROM inventory WHERE inventory_id = ?');
        $stmt->execute([$inventoryId]);
        $item = $stmt->fetch();

        if (!$item) {
            flash('error', 'Item not found.');
            redirect('inventory.php');
        }

        // Cascade-delete logs first (in case FK constraints are not set up)
        $pdo->prepare('DELETE FROM inventory_logs WHERE inventory_id = ?')->execute([$inventoryId]);
        $pdo->prepare('DELETE FROM inventory        WHERE inventory_id = ?')->execute([$inventoryId]);

        flash('success', '"' . $item->item_name . '" removed from inventory.');
        redirect('inventory.php');
    }
}

// ============================================================
// FETCH INVENTORY
// ============================================================
$stmt = $pdo->query('SELECT * FROM inventory ORDER BY item_name');
$rawItems = $stmt->fetchAll();

$inventoryItems = [];
foreach ($rawItems as $item) {
    $pct   = $item->max_quantity > 0 ? min(100, round(($item->quantity / $item->max_quantity) * 100)) : 0;
    $ratio = $item->max_quantity > 0 ? ($item->quantity / $item->max_quantity) : 1;

    $item->status = match (true) {
        $ratio <= 0.2 => 'critical',
        $ratio <= 0.4 => 'low',
        default       => 'ok',
    };

    $item->pct = $pct;
    $item->max = $item->max_quantity;
    $inventoryItems[] = $item;
}

$lowStockAlertCount = count(array_filter($inventoryItems, fn($i) => in_array($i->status, ['low', 'critical'])));

// Recent restock logs
$stmt = $pdo->query(
    "SELECT l.*, i.item_name, i.unit
     FROM inventory_logs l
     JOIN inventory i ON i.inventory_id = l.inventory_id
     ORDER BY l.date_restocked DESC, l.log_id DESC
     LIMIT 5"
);
$recentUpdates = $stmt->fetchAll();

// ============================================================
// RENDER
// ============================================================
$pageTitle = 'Inventory';
$activeNav = 'inventory';
require __DIR__ . '/includes/header_app.php';
?>

<!-- ── Page Header ─────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <div class="breadcrumb">
            <a href="dashboard.php">Home</a>
            <span>&rsaquo;</span>
            <span class="current">Inventory</span>
        </div>
        <h1 class="page-title">Inventory Management</h1>
        <p class="page-subtitle">Track and manage laundry supply stock levels</p>
    </div>
    <!-- Add New Item button -->
    <button class="btn btn-primary" onclick="openModal('modal-add-item')" aria-haspopup="dialog">
        <i class="ph-bold ph-plus" aria-hidden="true"></i>
        Add Item
    </button>
</div>

<!-- ── Flash Messages ──────────────────────────────────────── -->
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

<!-- ── Low Stock Banner ────────────────────────────────────── -->
<?php if ($lowStockAlertCount > 0): ?>
    <div class="alert alert-warning" role="alert">
        <i class="ph-bold ph-warning" aria-hidden="true"></i>
        <span>
            <strong><?= $lowStockAlertCount ?> item<?= $lowStockAlertCount > 1 ? 's are' : ' is' ?> running low.</strong>
            Consider restocking soon.
        </span>
    </div>
<?php endif; ?>

<!-- ── Two-Column Layout ───────────────────────────────────── -->
<div style="display:grid; grid-template-columns:1fr 380px; gap:var(--spacing-lg); align-items:start;">

    <!-- LEFT — Supply Stock Levels -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Supply Stock Levels</span>
            <span class="text-muted" style="font-size:12px;"><?= count($inventoryItems) ?> item<?= count($inventoryItems) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="card-body" style="padding-top:var(--spacing-sm);">

            <?php if (empty($inventoryItems)): ?>
                <p class="text-muted text-sm" style="text-align:center; padding:var(--spacing-lg) 0;">
                    No inventory items yet. <button class="btn-link" onclick="openModal('modal-add-item')">Add the first one.</button>
                </p>
            <?php endif; ?>

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
                            <div class="inv-bar-fill <?= $barClass ?>" style="width:<?= (int)$item->pct ?>%;"></div>
                        </div>
                    </div>
                    <div class="inv-qty">
                        <div class="qty-val <?= $qtyClass ?>"><?= (int)$item->quantity ?></div>
                        <div class="qty-label <?= $qtyClass ?>"><?= h($statusLabel) ?></div>
                    </div>
                    <!-- Action buttons -->
                    <div style="display:flex; gap:4px; flex-shrink:0;">
                        <!-- Restock -->
                        <button class="action-btn edit"
                                onclick="selectInventoryItem(<?= (int)$item->inventory_id ?>, <?= (int)$item->quantity ?>)"
                                aria-label="Restock <?= h($item->item_name) ?>"
                                title="Restock">
                            <i class="ph-bold ph-plus-circle"></i>
                        </button>
                        <!-- Edit details -->
                        <button class="action-btn"
                                style="color:var(--color-text-muted);"
                                onclick='openEditModal(<?= json_encode([
                                    "inventory_id" => $item->inventory_id,
                                    "item_name"    => $item->item_name,
                                    "unit"         => $item->unit,
                                    "icon"         => $item->icon,
                                    "max_quantity" => $item->max_quantity,
                                ]) ?>)'
                                aria-label="Edit <?= h($item->item_name) ?>"
                                title="Edit item details">
                            <i class="ph-bold ph-pencil-simple"></i>
                        </button>
                        <!-- Delete -->
                        <button class="action-btn"
                                style="color:var(--color-danger, #e53e3e);"
                                onclick="confirmDelete(<?= (int)$item->inventory_id ?>, '<?= h(addslashes($item->item_name)) ?>')"
                                aria-label="Delete <?= h($item->item_name) ?>"
                                title="Delete item">
                            <i class="ph-bold ph-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </div><!-- /LEFT -->

    <!-- RIGHT — Update Stock Panel -->
    <div class="card" style="position:sticky; top:calc(var(--topbar-height) + var(--spacing-lg));">
        <div class="card-header">
            <span class="card-title">Restock Item</span>
        </div>
        <div class="card-body">

            <form method="POST" action="inventory.php" id="form-update-stock" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="restock">

                <div class="form-group">
                    <label class="form-label" for="select-item">Select Item</label>
                    <select id="select-item" name="inventory_id" class="form-control" required
                            aria-required="true" onchange="onItemSelect(this)">
                        <option value="">Choose an item&hellip;</option>
                        <?php foreach ($inventoryItems as $item): ?>
                            <option value="<?= (int)$item->inventory_id ?>"
                                    data-qty="<?= (int)$item->quantity ?>">
                                <?= h($item->item_name) ?> — <?= (int)$item->quantity ?> <?= h($item->unit) ?>s remaining
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="current-qty">Current Qty</label>
                        <input type="number" id="current-qty" class="form-control"
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
                    <label class="form-label" for="remarks">Remarks <span class="text-muted">(optional)</span></label>
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
                    Recent Restocks
                </div>
                <?php if (empty($recentUpdates)): ?>
                    <p class="text-muted text-sm">No restock history yet.</p>
                <?php else: ?>
                    <?php foreach ($recentUpdates as $update): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center;
                                    padding:8px 0; border-bottom:1px solid var(--color-border-light); font-size:12px;">
                            <span style="font-weight:500;"><?= h($update->item_name) ?> (+<?= (int)$update->add_quantity ?>)</span>
                            <span class="text-muted"><?= date('M j, Y', strtotime($update->date_restocked)) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div><!-- /RIGHT -->

</div><!-- /.two-column -->


<!-- ============================================================
     MODAL — Add New Item
     ============================================================ -->
<div id="modal-add-item" class="modal-backdrop" role="dialog" aria-modal="true"
     aria-labelledby="modal-add-title" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <h2 class="modal-title" id="modal-add-title">
                <i class="ph-bold ph-plus-circle" aria-hidden="true"></i> Add New Item
            </h2>
            <button class="modal-close" onclick="closeModal('modal-add-item')" aria-label="Close dialog">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" action="inventory.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="create">

                <div class="grid-2">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label" for="new-item-name">Item Name <span class="text-danger">*</span></label>
                        <input type="text" id="new-item-name" name="item_name" class="form-control"
                               placeholder="e.g. Fabric Softener" maxlength="100" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="new-unit">Unit <span class="text-danger">*</span></label>
                        <input type="text" id="new-unit" name="unit" class="form-control"
                               placeholder="e.g. bottle, sachet, kg" maxlength="30" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="new-icon">Icon (emoji)</label>
                        <input type="text" id="new-icon" name="icon" class="form-control"
                               placeholder="🧴" maxlength="4" value="📦">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="new-quantity">Initial Quantity <span class="text-danger">*</span></label>
                        <input type="number" id="new-quantity" name="quantity" class="form-control"
                               placeholder="0" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="new-max">Max Quantity <span class="text-danger">*</span></label>
                        <input type="number" id="new-max" name="max_quantity" class="form-control"
                               placeholder="e.g. 50" min="1" required>
                    </div>
                </div>

                <div style="display:flex; gap:var(--spacing-sm); justify-content:flex-end; margin-top:var(--spacing-md);">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add-item')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ph-bold ph-plus" aria-hidden="true"></i> Add Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================================
     MODAL — Edit Item Details
     ============================================================ -->
<div id="modal-edit-item" class="modal-backdrop" role="dialog" aria-modal="true"
     aria-labelledby="modal-edit-title" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <h2 class="modal-title" id="modal-edit-title">
                <i class="ph-bold ph-pencil-simple" aria-hidden="true"></i> Edit Item
            </h2>
            <button class="modal-close" onclick="closeModal('modal-edit-item')" aria-label="Close dialog">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" action="inventory.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="edit">
                <input type="hidden" name="inventory_id" id="edit-inventory-id">

                <div class="grid-2">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label" for="edit-item-name">Item Name <span class="text-danger">*</span></label>
                        <input type="text" id="edit-item-name" name="item_name" class="form-control"
                               maxlength="100" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-unit">Unit <span class="text-danger">*</span></label>
                        <input type="text" id="edit-unit" name="unit" class="form-control"
                               maxlength="30" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-icon">Icon (emoji)</label>
                        <input type="text" id="edit-icon" name="icon" class="form-control" maxlength="4">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label" for="edit-max">Max Quantity <span class="text-danger">*</span></label>
                        <input type="number" id="edit-max" name="max_quantity" class="form-control" min="1" required>
                        <small class="text-muted">Current quantity is unchanged. Only the maximum cap is updated here.</small>
                    </div>
                </div>

                <div style="display:flex; gap:var(--spacing-sm); justify-content:flex-end; margin-top:var(--spacing-md);">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-item')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ph-bold ph-check" aria-hidden="true"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================================
     MODAL — Delete Confirmation
     ============================================================ -->
<div id="modal-delete-item" class="modal-backdrop" role="dialog" aria-modal="true"
     aria-labelledby="modal-delete-title" style="display:none;">
    <div class="modal-box modal-box--sm">
        <div class="modal-header">
            <h2 class="modal-title" id="modal-delete-title" style="color:var(--color-danger,#e53e3e);">
                <i class="ph-bold ph-trash" aria-hidden="true"></i> Delete Item
            </h2>
            <button class="modal-close" onclick="closeModal('modal-delete-item')" aria-label="Close dialog">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete <strong id="delete-item-name"></strong>?
               This will also remove all restock history for this item. This action cannot be undone.</p>

            <form method="POST" action="inventory.php" style="margin-top:var(--spacing-md);">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="inventory_id" id="delete-inventory-id">

                <div style="display:flex; gap:var(--spacing-sm); justify-content:flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-delete-item')">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="ph-bold ph-trash" aria-hidden="true"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================================
     INLINE STYLES — Modal & misc additions
     (drop this block into your main stylesheet if preferred)
     ============================================================ -->
<style>
/* ── Modal backdrop ─────────────────────────────────────────── */
.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1000;
    background: rgba(0, 0, 0, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    animation: fadeIn .15s ease;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.modal-box {
    background: var(--color-surface, #fff);
    border-radius: var(--radius-lg, 12px);
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
    width: 100%;
    max-width: 520px;
    animation: slideUp .18s ease;
    overflow: hidden;
}
.modal-box--sm { max-width: 400px; }
@keyframes slideUp { from { transform: translateY(12px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--spacing-md, 1rem) var(--spacing-md, 1rem) 0;
}
.modal-title {
    font-size: 1rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: .4rem;
}
.modal-body {
    padding: var(--spacing-md, 1rem);
}
.modal-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    border-radius: 6px;
    color: var(--color-text-muted);
    line-height: 1;
    font-size: 1.1rem;
    transition: background .15s;
}
.modal-close:hover { background: var(--color-border-light); }

/* ── Danger button ──────────────────────────────────────────── */
.btn-danger {
    background: var(--color-danger, #e53e3e);
    color: #fff;
    border: none;
    padding: var(--btn-padding-y, .5rem) var(--btn-padding-x, 1rem);
    border-radius: var(--radius-md, 8px);
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    transition: filter .15s;
}
.btn-danger:hover { filter: brightness(.9); }

/* ── Btn-link (inline text button) ─────────────────────────── */
.btn-link {
    background: none;
    border: none;
    color: var(--color-primary, #3b82f6);
    cursor: pointer;
    font-size: inherit;
    padding: 0;
    text-decoration: underline;
}

/* ── Trap focus: prevent background scroll when modal open ── */
body.modal-open { overflow: hidden; }
</style>


<!-- ============================================================
     PAGE SCRIPTS
     ============================================================ -->
<?php
$pageScripts = <<<'HTML'
<script>
// ── Restock panel helpers ──────────────────────────────────────
function selectInventoryItem(id, qty) {
    const select  = document.getElementById('select-item');
    const current = document.getElementById('current-qty');
    select.value  = id;
    current.value = qty;
    current.removeAttribute('disabled');
    document.getElementById('form-update-stock').scrollIntoView({ behavior: 'smooth', block: 'start' });
    document.getElementById('add-qty').focus();
}

function onItemSelect(selectEl) {
    const opt       = selectEl.options[selectEl.selectedIndex];
    const qty       = opt.dataset.qty;
    const currentEl = document.getElementById('current-qty');
    if (qty !== undefined) {
        currentEl.value = qty;
        currentEl.removeAttribute('disabled');
    } else {
        currentEl.value = '';
    }
}

// ── Modal helpers ──────────────────────────────────────────────
function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = 'flex';
    document.body.classList.add('modal-open');
    // Focus first focusable element
    const first = el.querySelector('input:not([type=hidden]), select, textarea, button');
    if (first) first.focus();
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = 'none';
    document.body.classList.remove('modal-open');
}

// Close on backdrop click
document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', e => {
        if (e.target === backdrop) closeModal(backdrop.id);
    });
});

// Close on Escape key
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.modal-backdrop').forEach(m => {
        if (m.style.display !== 'none') closeModal(m.id);
    });
});

// ── Edit modal ─────────────────────────────────────────────────
function openEditModal(item) {
    document.getElementById('edit-inventory-id').value = item.inventory_id;
    document.getElementById('edit-item-name').value    = item.item_name;
    document.getElementById('edit-unit').value         = item.unit;
    document.getElementById('edit-icon').value         = item.icon;
    document.getElementById('edit-max').value          = item.max_quantity;
    openModal('modal-edit-item');
}

// ── Delete confirmation ────────────────────────────────────────
function confirmDelete(id, name) {
    document.getElementById('delete-inventory-id').value = id;
    document.getElementById('delete-item-name').textContent = name;
    openModal('modal-delete-item');
}
</script>
HTML;
require __DIR__ . '/includes/footer_app.php';