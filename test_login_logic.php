<?php
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
