<?php
/**
 * test_login_logic.php
 * Run with: php test_login_logic.php
 *
 * Exercises evaluate_login() and role_home_page() the same way the
 * original inline conditional in login.php was traced by hand in the
 * baseline table (see SE5-Lab2-submission-draft.md, section 1).
 */

declare(strict_types=1);
require __DIR__ . '/login_functions.php';

$passed = 0;
$failed = 0;

/**
 * Small helper so failures print something useful instead of just
 * halting on assert(). Not a testing framework — just enough to see
 * before/after results at a glance.
 */
function check(string $label, $actual, $expected): void
{
    global $passed, $failed;
    if ($actual === $expected) {
        $passed++;
        echo "[PASS] {$label}\n";
    } else {
        $failed++;
        echo "[FAIL] {$label} — expected " . var_export($expected, true)
            . ", got " . var_export($actual, true) . "\n";
    }
}

function make_user(int $active, string $plainPassword): object
{
    return (object)[
        'is_active' => $active,
        'password'  => password_hash($plainPassword, PASSWORD_DEFAULT),
        'role'      => 'staff', // not used by evaluate_login, kept for realism
    ];
}

echo "Running login-logic tests...\n\n";

// --- evaluate_login() ---

$active   = make_user(1, 'correct-horse');
$inactive = make_user(0, 'correct-horse');

check('#1 active user, correct password -> success',
    evaluate_login($active, 'correct-horse'), 'success');

check('#2 active user, wrong password -> invalid',
    evaluate_login($active, 'wrong-pass'), 'invalid');

check('#3 inactive user, correct password -> inactive',
    evaluate_login($inactive, 'correct-horse'), 'inactive');

check('#4 no matching user (null) -> invalid',
    evaluate_login(null, 'anything'), 'invalid');

// edge case: inactive account + wrong password should still report
// "inactive" first (account status is checked before the password),
// matching the original elseif ordering.
check('#5 (edge) inactive user, wrong password -> inactive',
    evaluate_login($inactive, 'wrong-pass'), 'inactive');

// --- role_home_page() ---

check('#6 owner role -> dashboard.php',
    role_home_page('owner'), 'dashboard.php');

check('#7 staff role -> staff_dashboard.php',
    role_home_page('staff'), 'staff_dashboard.php');

check('#8 any other role -> staff_dashboard.php (default branch)',
    role_home_page('cashier'), 'staff_dashboard.php');

echo "\n{$passed} passed, {$failed} failed.\n";

exit($failed > 0 ? 1 : 0);
