<?php
/**
 * index.php
 * Takines Labada Hub — Entry point
 * Redirects to the login page, or the user's home page if already signed in.
 */
require_once __DIR__ . '/config.php';

$user = current_user();

if (!$user) {
    redirect('login.php');
}

redirect($user->role === 'owner' ? 'dashboard.php' : 'staff_dashboard.php');
