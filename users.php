<?php
/**
 * users.php
 * Takines Labada Hub — User Management
 * Owner only — manage Owner / Staff accounts
 */
require_once __DIR__ . '/config.php';
require_role('owner');

$currentUser = current_user();

// ------------------------------------------------------------
// HANDLE FORM SUBMISSIONS
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $method = $_POST['_method'] ?? 'POST';

    // Toggle active / inactive
    if ($method === 'TOGGLE') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id === $currentUser->id) {
            flash('error', 'You cannot deactivate your own account.');
            redirect('users.php');
        }
        $stmt = $pdo->prepare('UPDATE users SET is_active = NOT is_active WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'User status updated.');
        redirect('users.php');
    }

    // Delete
    if ($method === 'DELETE') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id === $currentUser->id) {
            flash('error', 'You cannot delete your own account.');
            redirect('users.php');
        }
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'User account deleted.');
        redirect('users.php');
    }

    // Add / Edit
    $name     = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role     = $_POST['role'] ?? 'staff';
    $password = (string)($_POST['password'] ?? '');

    $errors = [];
    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if ($username === '' || !preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3-50 characters (letters, numbers, underscores, periods only).';
    }
    if (!in_array($role, ['owner', 'staff'], true)) {
        $errors[] = 'Please select a valid role.';
    }

    if ($method === 'PUT') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($password !== '' && strlen($password) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('users.php');
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            flash('error', 'That username is already taken.');
            redirect('users.php');
        }

        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET name=?, username=?, role=?, password=? WHERE id=?');
            $stmt->execute([$name, $username, $role, $hash, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET name=?, username=?, role=? WHERE id=?');
            $stmt->execute([$name, $username, $role, $id]);
        }

        // Keep session in sync if editing self
        if ($id === $currentUser->id) {
            $_SESSION['user']->name = $name;
            $_SESSION['user']->role = $role;
        }

        flash('success', 'User account updated.');
    } else {
        if ($password === '' || strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('users.php');
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            flash('error', 'That username is already taken.');
            redirect('users.php');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (name, username, password, role) VALUES (?,?,?,?)');
        $stmt->execute([$name, $username, $hash, $role]);
        flash('success', 'User account created.');
    }

    redirect('users.php');
}

// ------------------------------------------------------------
// FETCH USERS
// ------------------------------------------------------------
$stmt = $pdo->query('SELECT id, name, username, role, is_active, created_at FROM users ORDER BY role, name');
$users = $stmt->fetchAll();

$pageTitle = 'User Management';
$activeNav = 'users';
require __DIR__ . '/includes/header_app.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <div class="breadcrumb">
            <a href="dashboard.php">Home</a>
            <span>&rsaquo;</span>
            <span class="current">User Management</span>
        </div>
        <h1 class="page-title">User Management</h1>
        <p class="page-subtitle">Manage Owner and Staff accounts</p>
    </div>
    <button class="btn btn-primary" id="btn-add-user" aria-haspopup="dialog">
        <i class="ph-bold ph-plus" aria-hidden="true"></i>
        + Add User
    </button>
</div>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success" role="alert"><i class="ph-bold ph-check-circle"></i> <?= h($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
    <div class="alert alert-danger" role="alert"><i class="ph-bold ph-warning"></i> <?= h($msg) ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-container" style="border:none; border-radius:0;">
        <table class="data-table" aria-label="User accounts">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Username</th>
                    <th scope="col">Role</th>
                    <th scope="col">Status</th>
                    <th scope="col">Created</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <?= h($u->name) ?>
                            <?php if ($u->id === $currentUser->id): ?>
                                <span class="badge badge-gray">You</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($u->username) ?></td>
                        <td>
                            <span class="badge <?= $u->role === 'owner' ? 'badge-blue' : 'badge-teal' ?>">
                                <?= h(ucfirst($u->role)) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $u->is_active ? 'badge-green' : 'badge-gray' ?>">
                                <?= $u->is_active ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="text-muted text-sm"><?= date('M j, Y', strtotime($u->created_at)) ?></td>
                        <td>
                            <button class="action-btn edit"
                                    onclick='openEditUser(<?= json_encode([
                                        "id" => $u->id,
                                        "name" => $u->name,
                                        "username" => $u->username,
                                        "role" => $u->role,
                                    ]) ?>)'
                                    aria-label="Edit user" title="Edit">
                                <i class="ph-bold ph-pencil-simple"></i>
                            </button>

                            <?php if ($u->id !== $currentUser->id): ?>
                                <form method="POST" action="users.php" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_method" value="TOGGLE">
                                    <input type="hidden" name="user_id" value="<?= (int)$u->id ?>">
                                    <button type="submit" class="action-btn edit"
                                            title="<?= $u->is_active ? 'Deactivate' : 'Activate' ?>"
                                            aria-label="<?= $u->is_active ? 'Deactivate user' : 'Activate user' ?>">
                                        <i class="ph-bold ph-power"></i>
                                    </button>
                                </form>
                                <form method="POST" action="users.php" style="display:inline;"
                                      onsubmit="return confirm('Delete this user account? This cannot be undone.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_method" value="DELETE">
                                    <input type="hidden" name="user_id" value="<?= (int)$u->id ?>">
                                    <button type="submit" class="action-btn delete" aria-label="Delete user" title="Delete">
                                        <i class="ph-bold ph-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- ADD USER MODAL -->
<div class="modal-overlay" id="modal-add-user" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="add-user-title">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title" id="add-user-title"><span aria-hidden="true">👤</span> Add User</div>
            <button class="modal-close" id="btn-close-add-user" aria-label="Close"><i class="ph-bold ph-x"></i></button>
        </div>
        <form method="POST" action="users.php" novalidate>
            <?= csrf_field() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="add-name">Full Name</label>
                    <input type="text" id="add-name" name="name" class="form-control" required aria-required="true" maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label" for="add-username">Username</label>
                    <input type="text" id="add-username" name="username" class="form-control" required aria-required="true" maxlength="50">
                </div>
                <div class="form-group">
                    <label class="form-label" for="add-role">Role</label>
                    <select id="add-role" name="role" class="form-control" required aria-required="true">
                        <option value="staff">Staff</option>
                        <option value="owner">Owner</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="add-password">Password</label>
                    <input type="password" id="add-password" name="password" class="form-control" required aria-required="true" minlength="8">
                    <small class="text-muted">At least 8 characters.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="btn-cancel-add-user">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="ph-bold ph-check"></i> Create User</button>
            </div>
        </form>
    </div>
</div>


<!-- EDIT USER MODAL -->
<div class="modal-overlay" id="modal-edit-user" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="edit-user-title">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title" id="edit-user-title"><span aria-hidden="true">✏️</span> Edit User</div>
            <button class="modal-close" id="btn-close-edit-user" aria-label="Close"><i class="ph-bold ph-x"></i></button>
        </div>
        <form method="POST" action="users.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="PUT">
            <input type="hidden" name="user_id" id="edit-user-id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit-name">Full Name</label>
                    <input type="text" id="edit-name" name="name" class="form-control" required aria-required="true" maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-username">Username</label>
                    <input type="text" id="edit-username" name="username" class="form-control" required aria-required="true" maxlength="50">
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-role">Role</label>
                    <select id="edit-role" name="role" class="form-control" required aria-required="true">
                        <option value="staff">Staff</option>
                        <option value="owner">Owner</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-password">New Password (optional)</label>
                    <input type="password" id="edit-password" name="password" class="form-control" minlength="8" placeholder="Leave blank to keep current password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="btn-cancel-edit-user">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="ph-bold ph-check"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php
$pageScripts = <<<'HTML'
<script>
    const addUserModal = document.getElementById('modal-add-user');
    document.getElementById('btn-add-user').addEventListener('click', () => addUserModal.style.display = 'flex');
    document.getElementById('btn-close-add-user').addEventListener('click', () => addUserModal.style.display = 'none');
    document.getElementById('btn-cancel-add-user').addEventListener('click', () => addUserModal.style.display = 'none');
    addUserModal.addEventListener('click', e => { if (e.target === addUserModal) addUserModal.style.display = 'none'; });

    const editUserModal = document.getElementById('modal-edit-user');
    document.getElementById('btn-close-edit-user').addEventListener('click', () => editUserModal.style.display = 'none');
    document.getElementById('btn-cancel-edit-user').addEventListener('click', () => editUserModal.style.display = 'none');
    editUserModal.addEventListener('click', e => { if (e.target === editUserModal) editUserModal.style.display = 'none'; });

    function openEditUser(u) {
        document.getElementById('edit-user-id').value   = u.id;
        document.getElementById('edit-name').value      = u.name;
        document.getElementById('edit-username').value  = u.username;
        document.getElementById('edit-role').value      = u.role;
        document.getElementById('edit-password').value  = '';
        editUserModal.style.display = 'flex';
    }
</script>
HTML;
require __DIR__ . '/includes/footer_app.php';
