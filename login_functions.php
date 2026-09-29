<?php
/**
 * login_functions.php
 *
 * Login-decision helpers extracted from login.php (SE5 Lab 2).
 * No DB, session, or HTTP dependency. Required by login_refractor.php
 * and test_login_logic.php, so the tests exercise the same code the
 * page runs.
 */

/**
 * Decide the outcome of a login attempt.
 * Mirrors the original login.php conditions exactly:
 *   success  : user exists AND is_active === 1 AND password matches
 *   inactive : user exists AND is_active === 0 (wins over a wrong password)
 *   invalid  : everything else (no user, wrong password, any other is_active value)
 *
 * @return string 'success' | 'inactive' | 'invalid'
 */
function evaluate_login(?object $user, string $password): string
{
    if (!$user) {
        return 'invalid';
    }
    if ((int)$user->is_active === 1 && password_verify($password, $user->password)) {
        return 'success';
    }
    if ((int)$user->is_active === 0) {
        return 'inactive';
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
