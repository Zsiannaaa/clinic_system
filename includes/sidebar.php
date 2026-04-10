<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Access denied.');
}

// ============================================================
// includes/sidebar.php — Role-Aware Navigation Menu
//
// This builds the sidebar navigation dynamically based on
// the logged-in user's role stored in $_SESSION['role'].
//
// Role behavior:
//   admin       — sees Dashboard, Doctors, Patients, Appointments
//   receptionist — sees Dashboard, Doctors (view label), Patients, Appointments
//   doctor      — sees only Dashboard and My Appointments
//
// NOTE: Hiding links here is just for UX. The REAL permission
// enforcement is in each module's requireRole() call at the
// top of every PHP file. (Bonus 2 — hiding links is NOT enough)
// ============================================================
$role   = getUserRole(); // 'admin', 'receptionist', or 'doctor'

// sidebarActive() highlights the current page's nav link
// by adding the 'active' CSS class when the URL contains the segment
function sidebarActive(string $segment): string {
    return str_contains($_SERVER['PHP_SELF'] ?? '', $segment) ? 'active' : '';
}
?>

<p class="sidebar-section-label">Main</p>

<ul class="nav flex-column">
    <li class="nav-item">
        <a href="/clinic_1/public/dashboard.php"
           class="nav-link <?= sidebarActive('dashboard') ?>">
            <svg data-lucide="layout-dashboard" class="nav-icon"></svg>
            <span class="nav-label">Dashboard</span>
        </a>
    </li>
</ul>

<?php if (in_array($role, ['admin', 'receptionist'])): ?>

<div class="sidebar-divider"></div>
<p class="sidebar-section-label">Management</p>

<ul class="nav flex-column">

    <?php if ($role === 'admin'): ?>
    <li class="nav-item">
        <a href="/clinic_1/public/doctors.php"
           class="nav-link <?= sidebarActive('doctors') ?>">
            <svg data-lucide="stethoscope" class="nav-icon"></svg>
            <span class="nav-label">Doctors</span>
        </a>
    </li>
    <?php else: ?>
    <li class="nav-item">
        <a href="/clinic_1/public/doctors.php"
           class="nav-link <?= sidebarActive('doctors') ?>">
            <svg data-lucide="stethoscope" class="nav-icon"></svg>
            <span class="nav-label">Doctors <small class="nav-badge">view</small></span>
        </a>
    </li>
    <?php endif; ?>

    <li class="nav-item">
        <a href="/clinic_1/public/patients.php"
           class="nav-link <?= sidebarActive('patients') ?>">
            <svg data-lucide="users" class="nav-icon"></svg>
            <span class="nav-label">Patients</span>
        </a>
    </li>

    <li class="nav-item">
        <a href="/clinic_1/public/appointments.php"
           class="nav-link <?= sidebarActive('appointments') ?>">
            <svg data-lucide="calendar-check" class="nav-icon"></svg>
            <span class="nav-label">Appointments</span>
        </a>
    </li>

    <li class="nav-item">
        <a href="/clinic_1/public/queue.php"
           class="nav-link <?= sidebarActive('queue') ?>">
            <svg data-lucide="clock" class="nav-icon"></svg>
            <span class="nav-label">Queue Board</span>
        </a>
    </li>

    <li class="nav-item">
        <a href="/clinic_1/public/ehr.php"
           class="nav-link <?= sidebarActive('ehr') ?>">
            <svg data-lucide="file-text" class="nav-icon"></svg>
            <span class="nav-label">EHR Notes</span>
        </a>
    </li>

    <li class="nav-item">
        <a href="/clinic_1/public/billing.php"
           class="nav-link <?= sidebarActive('billing') ?>">
            <svg data-lucide="file-text" class="nav-icon"></svg>
            <span class="nav-label">Billing</span>
        </a>
    </li>

    <li class="nav-item">
        <a href="/clinic_1/public/reports.php"
           class="nav-link <?= sidebarActive('reports') ?>">
            <svg data-lucide="file-text" class="nav-icon"></svg>
            <span class="nav-label">Reports</span>
        </a>
    </li>

    <?php if ($role === 'admin'): ?>
    <li class="nav-item">
        <a href="/clinic_1/public/users.php"
           class="nav-link <?= sidebarActive('users') ?>">
            <svg data-lucide="shield" class="nav-icon"></svg>
            <span class="nav-label">User Accounts</span>
        </a>
    </li>
    <?php endif; ?>

</ul>

<?php elseif ($role === 'doctor'): ?>

<div class="sidebar-divider"></div>
<p class="sidebar-section-label">My Work</p>

<ul class="nav flex-column">
    <li class="nav-item">
        <a href="/clinic_1/public/appointments.php"
           class="nav-link <?= sidebarActive('appointments') ?>">
            <svg data-lucide="calendar-check" class="nav-icon"></svg>
            <span class="nav-label">My Appointments</span>
        </a>
    </li>
    <li class="nav-item">
        <a href="/clinic_1/public/queue.php"
           class="nav-link <?= sidebarActive('queue') ?>">
            <svg data-lucide="clock" class="nav-icon"></svg>
            <span class="nav-label">My Queue</span>
        </a>
    </li>
    <li class="nav-item">
        <a href="/clinic_1/public/ehr.php"
           class="nav-link <?= sidebarActive('ehr') ?>">
            <svg data-lucide="file-text" class="nav-icon"></svg>
            <span class="nav-label">EHR Notes</span>
        </a>
    </li>
</ul>

<?php endif; ?>

<div class="sidebar-divider"></div>
<p class="sidebar-section-label">AI Tools</p>
<div class="ai-promo-wrap">
    <a href="/clinic_1/public/medical_ai.php"
       class="ai-promo-card <?= sidebarActive('medical_ai') ? 'is-active' : '' ?>">
        <div class="ai-promo-head">
            <img src="/clinic_1/assets/images/logo.png" alt="Cryptalis AI" class="ai-promo-logo">
            <span class="ai-promo-chip">NEW</span>
        </div>
        <div class="ai-promo-title">Try our new Cryptalis AI</div>
        <span class="ai-promo-btn">Try Now</span>
    </a>
</div>

<div class="sidebar-divider"></div>
<ul class="nav flex-column">
    <li class="nav-item">
        <a href="/clinic_1/modules/auth/logout.php" class="nav-link" style="color:var(--danger)">
            <svg data-lucide="log-out" class="nav-icon"></svg>
            <span class="nav-label">Logout</span>
        </a>
    </li>
</ul>
