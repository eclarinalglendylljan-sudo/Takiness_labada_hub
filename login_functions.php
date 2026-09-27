<?php
/**
 * login_functions.php
 * Extracted, pure decision logic from login.php.
 * No DB, session, or HTML dependencies — safe to unit test directly.
 */

/**
 * Decide the outcome of a login attempt.
 *
 * @param object|null $user  The user record already fetched from the DB
 *                           (must have ->is_active and ->password), or null
 *                           if no matching username was found.
 * @param string $password   The plaintext password submitted by the user.
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
 *
 * @param string $role
 * @return string
 */
function role_home_page(string $role): string
{
    return $role === 'owner' ? 'dashboard.php' : 'staff_dashboard.php';
}
