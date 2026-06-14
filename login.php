<?php

require_once __DIR__ . '/config.php';

if (current_user()) {
    redirect(current_user()->role === 'owner' ? 'dashboard.php' : 'staff_dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $errors[] = 'Please enter both your username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $found = $stmt->fetch();

        if ($found && (int)$found->is_active === 1 && password_verify($password, $found->password)) {
   
            session_regenerate_id(true);

            $_SESSION['user'] = (object)[
                'id'   => (int)$found->id,
                'name' => $found->name,
                'role' => $found->role,
            ];

            redirect($found->role === 'owner' ? 'dashboard.php' : 'staff_dashboard.php');
        } elseif ($found && (int)$found->is_active === 0) {
            $_SESSION['flash']['error'] = 'This account has been deactivated. Please contact the owner.';
        } else {
            
            $errors[] = 'Invalid username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Takines Labada Hub</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <script src="https://unpkg.com/@phosphor-icons/web@2.1.1/src/index.js" defer></script>
</head>
<body style="background: var(--color-surface);">

<div class="login-page">

   
    <div class="login-hero" aria-hidden="true">
        <div class="login-hero-content">
            <h1 class="login-hero-title">
                Takines<br>Labada Hub
            </h1>
            <p class="login-hero-sub">
                Laundry Shop Management System<br>
                <strong style="color:rgba(255,255,255,.9);">Smart. Simple. Spotless.</strong>
            </p>
        </div>
    </div>

   
    <div class="login-panel">

        <div class="login-logo">
            <div class="login-logo-img">
                <img src="assets/images/logo.png"
                     alt="Takines Labada Logo"
                     onerror="this.style.display='none'; this.parentNode.textContent='TL'">
            </div>
            <div class="login-logo-text">
                <div class="ln"><span>Takines</span> Labada</div>
                <div class="ls">Laundry Management</div>
            </div>
        </div>

        <h2 class="login-heading">Welcome back</h2>
        <p class="login-subheading">Sign in to your account to continue</p>

        <form method="POST" action="login.php" novalidate id="login-form">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <div class="login-input-wrapper">
                    <span class="login-input-icon" aria-hidden="true">
                        <i class="ph-bold ph-user"></i>
                    </span>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control <?= $errors ? 'is-error' : '' ?>"
                        value="<?= h($_POST['username'] ?? '') ?>"
                        autocomplete="username"
                        autofocus
                        placeholder="Enter your username"
                        required
                        maxlength="100"
                        aria-required="true"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="login-input-wrapper">
                    <span class="login-input-icon" aria-hidden="true">
                        <i class="ph-bold ph-lock"></i>
                    </span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        required
                        minlength="8"
                        aria-required="true"
                    >
                </div>
            </div>

            <div class="login-row">
                <label class="checkbox-label">
                    <input type="checkbox" name="remember" id="remember">
                    Remember me
                </label>
                <a href="#" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" class="btn-login" id="login-btn">
                Sign In
                <i class="ph-bold ph-arrow-right" aria-hidden="true"></i>
            </button>

            <?php if ($errors): ?>
                <div class="login-error" role="alert" aria-live="polite">
                    <i class="ph-bold ph-warning" aria-hidden="true"></i>
                    <span><?= h($errors[0]) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($msg = flash('error')): ?>
                <div class="login-error" role="alert" aria-live="polite">
                    <i class="ph-bold ph-warning" aria-hidden="true"></i>
                    <span><?= h($msg) ?></span>
                </div>
            <?php endif; ?>

        </form>

    </div>

</div>

<script>
    document.getElementById('login-form').addEventListener('submit', function () {
        const btn = document.getElementById('login-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="ph-bold ph-spinner"></i> Signing in&hellip;';
    });
</script>

</body>
</html>
