<?php
/**
 * test_login_logic.php  (SE5 Lab 2)
 *
 * Part A: baseline examples (same cases as the baseline table).
 * Part B: behavior comparison - original_login_decision() is a verbatim
 *         copy of the ORIGINAL login.php if/elseif/else (lines 30-44),
 *         reduced to returning a label instead of touching session/errors.
 *         Every user/password combination must give the same result from
 *         the original logic and from the refactored evaluate_login().
 *
 * Run: php test_login_logic.php   (exit code 0 = all passed)
 */
require __DIR__ . '/login_functions.php';

$passed = 0;
$failed = 0;

function check(string $label, $actual, $expected): void
{
    global $passed, $failed;
    if ($actual === $expected) {
        $passed++;
        echo "[PASS] $label\n";
    } else {
        $failed++;
        echo "[FAIL] $label (expected " . var_export($expected, true)
            . ', got ' . var_export($actual, true) . ")\n";
    }
}

function make_user(int $isActive, string $plain): object
{
    return (object)['is_active' => $isActive, 'password' => password_hash($plain, PASSWORD_DEFAULT)];
}

/** ORIGINAL login.php logic (before refactor), outcome only. */
function original_login_decision(?object $found, string $password): string
{
    if ($found && (int)$found->is_active === 1 && password_verify($password, $found->password)) {
        return 'success';
    } elseif ($found && (int)$found->is_active === 0) {
        return 'inactive';
    } else {
        return 'invalid';
    }
}

$right = 'correct-horse';
$wrong = 'wrong-pass';
$active   = make_user(1, $right);
$inactive = make_user(0, $right);
$odd      = make_user(2, $right);

echo "Running login-logic tests...\n\n";
echo "--- Part A: baseline examples ---\n";
check('#1 active user, correct password -> success',              evaluate_login($active, $right),   'success');
check('#2 active user, wrong password -> invalid',                evaluate_login($active, $wrong),   'invalid');
check('#3 inactive user, correct password -> inactive',           evaluate_login($inactive, $right), 'inactive');
check('#4 (edge) inactive user, wrong password -> inactive',      evaluate_login($inactive, $wrong), 'inactive');
check('#5 no matching user (null) -> invalid',                    evaluate_login(null, $right),      'invalid');
check('#6 (edge) is_active=2, correct password -> invalid',       evaluate_login($odd, $right),      'invalid');
check('#7 owner role -> dashboard.php',                           role_home_page('owner'),           'dashboard.php');
check('#8 staff role -> staff_dashboard.php',                     role_home_page('staff'),           'staff_dashboard.php');
check('#9 any other role -> staff_dashboard.php (default)',       role_home_page('admin'),           'staff_dashboard.php');

echo "\n--- Part B: original vs refactored, all combinations ---\n";
$users = ['null user' => null, 'active' => $active, 'inactive' => $inactive, 'is_active=2' => $odd];
$pwds  = ['correct password' => $right, 'wrong password' => $wrong];
foreach ($users as $uLabel => $u) {
    foreach ($pwds as $pLabel => $p) {
        check("same result: $uLabel + $pLabel",
              evaluate_login($u, $p), original_login_decision($u, $p));
    }
}
foreach (['owner', 'staff', 'admin', ''] as $role) {
    $orig = $role === 'owner' ? 'dashboard.php' : 'staff_dashboard.php'; // original ternary
    check("same redirect: role '$role'", role_home_page($role), $orig);
}

echo "\n$passed passed, $failed failed.\n";
exit($failed === 0 ? 0 : 1);
