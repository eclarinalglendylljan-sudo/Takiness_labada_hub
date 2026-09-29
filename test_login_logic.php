<?php
require __DIR__ . '/login_functions.php';

$failures = 0;

function check(string $label, $actual, $expected): void
{
    global $failures;
    if ($actual === $expected) {
        echo "PASS: $label\n";
    } else {
        $failures++;
        echo 'FAIL: ' . $label
            . ' (expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true) . ")\n";
    }
}
function make_user(int $isActive, string $plainPassword): object
{
    return (object)[
        'is_active' => $isActive,
        'password'  => password_hash($plainPassword, PASSWORD_DEFAULT),
    ];
}
$activeUser   = make_user(1, 'correct-horse');
$inactiveUser = make_user(0, 'correct-horse');
// 1. Active user, correct password -> success
check(
    'active user + correct password -> success',
    evaluate_login($activeUser, 'correct-horse'),
    'success'
);

// 2. Active user, wrong password -> invalid
check(
    'active user + wrong password -> invalid',
    evaluate_login($activeUser, 'wrong-pass'),
    'invalid'
);

// 3. Inactive user, correct password -> inactive
check(
    'inactive user + correct password -> inactive',
    evaluate_login($inactiveUser, 'correct-horse'),
    'inactive'
);

// 4. No matching user found -> invalid
check(
    'no user found -> invalid',
    evaluate_login(null, 'anything'),
    'invalid'
);

// 5. Edge case: inactive user with a WRONG password must still report
//    'inactive', not 'invalid' — the account-status check has to win
//    before the password is ever compared.
check(
    'inactive user + wrong password -> inactive (edge case)',
    evaluate_login($inactiveUser, 'wrong-pass'),
    'inactive'
);

// --- role_home_page() ---

check("role_home_page('owner') -> dashboard.php", role_home_page('owner'), 'dashboard.php');
check("role_home_page('staff') -> staff_dashboard.php", role_home_page('staff'), 'staff_dashboard.php');
check(
    "role_home_page('admin') -> staff_dashboard.php (non-owner default, edge case)",
    role_home_page('admin'),
    'staff_dashboard.php'
);

echo "\n";
if ($failures === 0) {
    echo "All tests passed.\n";
    exit(0);
}

echo "$failures test(s) failed.\n";
exit(1);
