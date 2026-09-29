<?php
/**
 * Refactored: login-decision logic lives in login_functions.php
 * (evaluate_login() and role_home_page()) so it can be unit tested
 * without a DB, session, or HTTP request. See test_login_logic.php.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/login_functions.php';

// Already logged in? send to the right home page.
if (current_user()) {
    redirect(role_home_page(current_user()->role));
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

        switch (evaluate_login($found, $password)) {
            case 'success':
                session_regenerate_id(true);

                $_SESSION['user'] = (object)[
                    'id'   => (int)$found->id,
                    'name' => $found->name,
                    'role' => $found->role,
                ];

                redirect(role_home_page($found->role));
                break;

            case 'inactive':
                $_SESSION['flash']['error'] = 'This account has been deactivated. Please contact the owner.';
                break;

            default:
                $errors[] = 'Invalid username or password. Please try again.';
        }
    }
}
?>
