<?php
/**
 * login.php
 * Takines Labada Hub — Login Screen
 * POST /login.php -> session with user_id + role
 * Generic error message (no username leak)
 */
require_once __DIR__ . '/config.php';

// Already logged in? send to the right home page.
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
    <title>Sign In Takines Labada Hub</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 1rem;
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 2.25rem 2rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
        }

        /* Logo space — your image will sit here */
        .logo-area {
            display: flex;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .logo-area img {
            height: 72px;
            width: auto;
            object-fit: contain;
        }

        /* Fallback initials if image fails */
        .logo-fallback {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #e8f5ef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            color: #1d9e75;
            letter-spacing: 1px;
        }

        .card-title {
            font-size: 22px;
            font-weight: 600;
            color: #111;
            margin-bottom: 0.25rem;
        }

        .card-sub {
            font-size: 14px;
            color: #888;
            margin-bottom: 1.75rem;
        }

        .field {
            margin-bottom: 1rem;
        }

        .field label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #444;
            margin-bottom: 6px;
        }

        .field-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .field-row label {
            font-size: 14px;
            font-weight: 500;
            color: #444;
        }

        .forgot {
            font-size: 13px;
            color: #1d9e75;
            text-decoration: none;
        }

        .forgot:hover { text-decoration: underline; }

        .input-wrap {
            position: relative;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            height: 46px;
            padding: 0 42px 0 14px;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            color: #111;
            background: #f7f8fa;
            outline: none;
            transition: border-color 0.15s;
            appearance: none;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #1d9e75;
            background: #f0faf6;
        }

        input.is-error {
            border-color: #e24b4a;
            background: #fff7f7;
        }

        .toggle-pw {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            color: #aaa;
            display: flex;
            align-items: center;
        }

        .toggle-pw svg { width: 18px; height: 18px; }

        .btn-signin {
            width: 100%;
            height: 48px;
            margin-top: 1.25rem;
            background: #1d9e75;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s, opacity 0.15s;
        }

        .btn-signin:hover { background: #178f68; }
        .btn-signin:active { background: #116b4e; }
        .btn-signin:disabled { opacity: 0.65; cursor: not-allowed; }

        .alert-error {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-top: 1rem;
            padding: 10px 14px;
            background: #fff2f2;
            border: 1px solid #f5c6c6;
            border-radius: 8px;
            font-size: 14px;
            color: #c0392b;
        }

        .alert-error svg { flex-shrink: 0; margin-top: 1px; }
    </style>
</head>
<body>

<div class="card">

    <!-- LOGO SPACE — swap src to your actual logo path -->
    <div class="logo-area">
        <img src="assets/images/logo.png"
             alt="Takines Labada Hub logo"
             onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='flex';">
        <div class="logo-fallback" id="logo-fallback" style="display:none;">TL</div>
    </div>

    <h1 class="card-title">Sign in to your account</h1>
    <p class="card-sub">Takines Labada Hub Laundry Management</p>

    <form method="POST" action="login.php" novalidate id="login-form">
        <?= csrf_field() ?>

        <div class="field">
            <label for="username">Username</label>
            <div class="input-wrap">
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="<?= $errors ? 'is-error' : '' ?>"
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

        <div class="field">
            <div class="field-row">
                <label for="password">Password</label>
                <a href="#" class="forgot">Forgot password?</a>
            </div>
            <div class="input-wrap">
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    placeholder="••••••••••••"
                    required
                    minlength="8"
                    aria-required="true"
                >
                <button type="button" class="toggle-pw" id="toggle-pw" aria-label="Toggle password visibility">
                    <!-- eye-off icon (default: hidden) -->
                    <svg id="icon-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-5 0-9-4-9-7a9.77 9.77 0 012.167-4.396M6.343 6.343A9.956 9.956 0 0112 5c5 0 9 4 9 7a9.74 9.74 0 01-3.13 4.743M9.878 9.878A3 3 0 0014.12 14.12M3 3l18 18"/>
                    </svg>
                    <!-- eye icon (shown when password is visible) -->
                    <svg id="icon-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" style="display:none;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-signin" id="login-btn">Sign in</button>

        <?php if ($errors): ?>
            <div class="alert-error" role="alert" aria-live="polite">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                <span><?= h($errors[0]) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($msg = flash('error')): ?>
            <div class="alert-error" role="alert" aria-live="polite">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                <span><?= h($msg) ?></span>
            </div>
        <?php endif; ?>

    </form>

</div>

<script>
    // Submit loading state
    document.getElementById('login-form').addEventListener('submit', function () {
        const btn = document.getElementById('login-btn');
        btn.disabled = true;
        btn.textContent = 'Signing in…';
    });

    // Password show/hide toggle
    const pwInput   = document.getElementById('password');
    const toggleBtn = document.getElementById('toggle-pw');
    const iconOff   = document.getElementById('icon-eye-off');
    const iconOn    = document.getElementById('icon-eye');

    toggleBtn.addEventListener('click', function () {
        const isHidden = pwInput.type === 'password';
        pwInput.type   = isHidden ? 'text' : 'password';
        iconOff.style.display = isHidden ? 'none'  : '';
        iconOn.style.display  = isHidden ? ''      : 'none';
    });
</script>

</body>
</html>
