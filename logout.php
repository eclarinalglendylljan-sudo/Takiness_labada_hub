<?php
/**
 * logout.php
 * Destroys the session and returns to the login screen.
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

$_SESSION = [];
session_destroy();
redirect('login.php');
