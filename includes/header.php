<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Access denied.');
}

// ============================================================
// includes/header.php — Shared Page Header & Sidebar Layout
//
// Included at the top of EVERY protected page.
// It does three things:
//   1. Calls requireLogin() — if not logged in, redirected before
//      a single pixel of HTML is shown (Bonus 1).
//   2. Outputs the HTML <head>, sidebar, and topbar.
//   3. Opens the <main> tag — each page adds its content,
//      then includes footer.php to close everything.
//
// This single shared file means NO duplicated layout code
// across modules — that's the modular design principle.
// ============================================================
require_once __DIR__ . '/auth.php';
requireLogin(); // Gate: non-logged-in users never see this HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cryptalis Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/clinic_1/assets/css/style.css" rel="stylesheet">
</head>
<body>
<script>
    // Bonus 3 — bfcache (back-forward cache) fix.
    // Modern browsers take a memory snapshot of a page and restore it
    // on Back button WITHOUT making a new HTTP request — bypassing PHP
    // session checks and no-cache headers entirely.
    // The 'pageshow' event fires every time a page is displayed,
    // including bfcache restores. event.persisted === true means
    // the page came from bfcache, not a fresh server request.
    // We force a reload so PHP runs again, checks the session,
    // and redirects to login if the user has logged out.
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>

<!-- Mobile sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <img src="/clinic_1/assets/images/logo.png" alt="Cryptalis Clinic" class="sidebar-logo">
        <span class="sidebar-brand-name">Cryptalis Clinic</span>
    </div>

    <!-- Nav links -->
    <div class="sidebar-nav-wrap">
        <nav class="sidebar-nav">
            <?php require_once __DIR__ . '/sidebar.php'; ?>
        </nav>
    </div>

    <!-- Bottom user info -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                <div class="sidebar-user-role"><?= ucfirst(getUserRole()) ?></div>
            </div>
        </div>
    </div>

</aside>
<!-- /SIDEBAR -->

<!-- ══ MAIN WRAPPER ══ -->
<div class="main-wrapper" id="mainWrapper">

    <!-- TOPBAR -->
    <header class="topbar">
        <button class="topbar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
            <svg data-lucide="menu" width="20" height="20"></svg>
        </button>

        <div class="topbar-spacer"></div>

        <div class="topbar-actions">
            <div class="topbar-date d-none d-md-flex">
                <svg data-lucide="calendar" width="14" height="14"></svg>&nbsp;
                <span><?= date('l, F j, Y') ?></span>
            </div>
            <div class="topbar-divider"></div>
           <!--  <div class="dropdown">
                <button class="topbar-user-btn" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
                    <div class="topbar-user-info d-none d-md-block">
                        <span class="topbar-user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
                        <span class="topbar-user-role"><?= ucfirst(getUserRole()) ?></span>
                    </div>
                    <svg data-lucide="chevron-down" width="13" height="13" style="color:var(--text-muted);margin-left:2px"></svg>
                </button>
                <ul class="dropdown-menu dropdown-menu-end topbar-dropdown">
                    <li>
                        <div class="dropdown-header">
                            <strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong>
                            <small><?= ucfirst(getUserRole()) ?></small>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="/clinic_1/modules/auth/logout.php">
                            <svg data-lucide="log-out" width="15" height="15" class="me-2"></svg>Logout
                        </a>
                    </li>
                </ul>
            </div> -->
        </div>
    </header>
    <!-- /TOPBAR -->

    <!-- PAGE CONTENT -->
    <main class="page-content">
        <div class="content-inner">
