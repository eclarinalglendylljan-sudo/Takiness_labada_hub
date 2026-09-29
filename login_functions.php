<?php
/**
 * login_functions.php
 *
 * Pure, DB/session/HTTP-independent login-decision helpers extracted
 * from login.php during the SE5 Lab 2 refactor. Included by both
 * login_refractor.php (production usage) and test_login_logic.php
 * (unit-style checks) so the decision logic can be verified without
 * a database, a session, or an HTTP request.
 */

/**
 * Decide the outcome of a login attempt.
 * @return string One of: 'success', 'inactive', 'invalid'
 */
function evaluate_login(?object $user, string $password): string
{
    if (!$user) {
        return 'invalid';
    }
    if ((int)$user->is_active === 0) {
        return 'inactive';
    }
    if (password_verify($password, $user->password)) {
        return 'success';
    }
    return 'invalid';
}

/**
 * Map a user role to their home page.
 */
function role_home_page(string $role): string
{
    return $role === 'owner' ? 'dashboard.php' : 'staff_dashboard.php';
}