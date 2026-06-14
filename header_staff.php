<?php
/**
 * includes/header_staff.php
 * Takines Labada Hub — Staff Portal Layout
 * Staff can ONLY record loads — no financial access
 */
require_login();
$user      = current_user();
$pageTitle = $pageTitle ?? 'Staff Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> — Takines Labada Hub</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <script src="https://unpkg.com/@phosphor-icons/web@2.1.1/src/index.js" defer></script>
</head>
<body>

<div class="staff-shell">

    <header class="staff-topbar" role="banner">
        <div class="staff-topbar-brand">
            <img src="assets/images/logo.png" alt="Logo"
                 style="width:30px; height:30px; border-radius:6px; object-fit:contain;"
                 onerror="this.style.display='none'">
            <div>
                <div class="brand-name" style="font-family:var(--font-primary); font-size:15px; font-weight:700; color:#fff;">
                    <span style="color:var(--color-primary)">Takines</span> Labada
                </div>
                <div class="brand-sub" style="font-size:10px; color:var(--sidebar-text); text-transform:uppercase; letter-spacing:.8px;">
                    Staff Portal
                </div>
            </div>
        </div>

        <div class="staff-topbar-right">
            <span class="staff-topbar-time" id="staff-clock"></span>

            <div class="staff-topbar-user">
                <div style="width:30px; height:30px; border-radius:50%; background:var(--color-primary);
                            display:flex; align-items:center; justify-content:center;
                            font-weight:700; font-size:12px; color:#fff;">
                    <?= h(strtoupper(substr($user->name ?? 'MS', 0, 2))) ?>
                </div>
                <div>
                    <div style="font-size:13px; font-weight:600; color:#fff;"><?= h($user->name ?? 'Maria Santos') ?></div>
                    <div style="font-size:10px; color:var(--sidebar-text);">Staff</div>
                </div>
            </div>

            <form method="POST" action="logout.php" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit"
                        style="padding:6px 14px; border-radius:6px; border:1px solid rgba(255,255,255,.2);
                               background:transparent; color:var(--sidebar-text); font-size:12px; font-weight:600; cursor:pointer;"
                        aria-label="Sign out">
                    <i class="ph-bold ph-sign-out"></i> Sign Out
                </button>
            </form>
        </div>
    </header>

    <main role="main" id="main-content">
