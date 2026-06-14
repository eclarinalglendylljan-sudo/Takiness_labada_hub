<?php
/**
 * includes/header_app.php
 * Takines Labada Hub — Main App Shell (Owner Layout)
 *
 * Expects (optional):
 *   $pageTitle   - string shown in <title> and used for the <title> tag
 *   $activeNav   - string key used to highlight the current sidebar item
 *                  one of: dashboard, sales, inventory, expenses, reports.income,
 *                  reports.tax, users, settings
 */
require_login();
$user       = current_user();
$pageTitle  = $pageTitle  ?? 'Dashboard';
$activeNav  = $activeNav  ?? '';

function nav_active(string $key, string $active): string
{
    return $key === $active ? 'active' : '';
}
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

<div class="app-shell">

    <!-- ======================================================
         SIDEBAR — Role-based navigation (Owner: full access)
         ====================================================== -->
    <aside class="sidebar" id="sidebar" role="navigation" aria-label="Main navigation">

        <!-- Brand -->
        <div class="sidebar-brand">
            <div class="sidebar-brand-logo" id="sidebar-logo-wrap">
                <img src="assets/images/logo.png"
                     alt="Takines Labada Logo"
                     id="sidebar-logo-img"
                     onerror="this.style.display='none'; document.getElementById('sidebar-logo-fallback').style.display='flex'">
                <span id="sidebar-logo-fallback"
                      style="display:none; width:34px; height:34px; align-items:center; justify-content:center; font-weight:800; font-size:14px; color:#fff;">
                    TL
                </span>
            </div>
            <div class="sidebar-brand-text">
                <div class="sidebar-brand-name">
                    <span>Takines</span> Labada
                </div>
                <div class="sidebar-brand-sub">Management</div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="sidebar-nav">

            <div class="sidebar-section-label">Main</div>

            <a href="dashboard.php" class="nav-item <?= nav_active('dashboard', $activeNav) ?>">
                <span class="nav-icon"><i class="ph-bold ph-house"></i></span>
                Dashboard
            </a>

            <a href="sales.php" class="nav-item <?= nav_active('sales', $activeNav) ?>">
                <span class="nav-icon"><i class="ph-bold ph-money"></i></span>
                Sales
            </a>

            <a href="inventory.php" class="nav-item <?= nav_active('inventory', $activeNav) ?>">
                <span class="nav-icon"><i class="ph-bold ph-package"></i></span>
                Inventory
            </a>

            <a href="expenses.php" class="nav-item <?= nav_active('expenses', $activeNav) ?>">
                <span class="nav-icon"><i class="ph-bold ph-receipt"></i></span>
                Expenses
            </a>

            <div class="sidebar-section-label">Reports</div>

            <a href="reports_income.php" class="nav-item <?= nav_active('reports.income', $activeNav) ?>">
                <span class="nav-icon"><i class="ph-bold ph-chart-bar"></i></span>
                Income &amp; Reports
            </a>

            <a href="reports_tax.php" class="nav-item <?= nav_active('reports.tax', $activeNav) ?>">
                <span class="nav-icon"><i class="ph-bold ph-file-text"></i></span>
                Tax Reports
            </a>

            <div class="sidebar-section-label">Admin</div>

            <a href="users.php" class="nav-item <?= nav_active('users', $activeNav) ?>">
                <span class="nav-icon"><i class="ph-bold ph-users-three"></i></span>
                User Management
            </a>

            <a href="settings.php" class="nav-item <?= nav_active('settings', $activeNav) ?>">
                <span class="nav-icon"><i class="ph-bold ph-gear"></i></span>
                Settings
            </a>

        </nav>

        <!-- User Footer -->
        <div class="sidebar-user">
            <div class="sidebar-user-avatar" aria-hidden="true">
                <?= h(strtoupper(substr($user->name ?? 'JP', 0, 2))) ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= h($user->name ?? 'Judith Piano') ?></div>
                <div class="sidebar-user-role"><?= h(ucfirst($user->role ?? 'Owner')) ?></div>
            </div>
            <form method="POST" action="logout.php">
                <?= csrf_field() ?>
                <button type="submit" class="sidebar-user-logout" title="Sign out" aria-label="Sign out">
                    <i class="ph-bold ph-sign-out"></i>
                </button>
            </form>
        </div>

    </aside>

    <!-- ======================================================
         MAIN CONTENT
         ====================================================== -->
    <div class="main-content">

        <!-- Topbar -->
        <header class="topbar" role="banner">
            <div class="topbar-brand">
                <img src="assets/images/logo.png" alt="Logo" class="topbar-brand-logo" onerror="this.style.display='none'">
                <div class="topbar-brand-text">
                    <div class="brand-name"><span>Takines</span> Labada</div>
                    <div class="brand-sub">Laundry Management</div>
                </div>
            </div>

            <div class="topbar-spacer"></div>

            <div class="topbar-actions">
                <button class="topbar-icon-btn" aria-label="Notifications">
                    <i class="ph-bold ph-bell"></i>
                </button>

                <div class="topbar-user">
                    <div class="topbar-user-info">
                        <div class="topbar-user-name"><?= h($user->name ?? 'Judith D. Piano') ?></div>
                        <div class="topbar-user-role"><?= h(strtoupper($user->role ?? 'Owner')) ?></div>
                    </div>
                    <div class="topbar-avatar" aria-hidden="true">
                        <?= h(strtoupper(substr($user->name ?? 'JP', 0, 2))) ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="page-content" id="main-content" role="main">
