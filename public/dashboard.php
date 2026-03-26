<?php
// ============================================================
// public/dashboard.php â€” Role-Based Dashboard (Controller + View)
//
// MVC Role:
//   Model      â†’ modules/dashboard_module.php (getAdminDashboardData, etc.)
//   Controller â†’ this file (calls the model, decides what to show)
//   View       â†’ HTML below (renders the data for the browser)
//
// Each role sees a completely different dashboard computed live.
// ============================================================
require_once '../includes/auth.php';
require_once '../modules/dashboard_module.php';
require_once '../modules/appointments_module.php';

requireLogin(); // Bonus 1: block non-logged-in users before any output

// Keep dashboard metrics clean by auto-cancelling stale scheduled appointments.
autoCancelOverdueAppointments($pdo, 24);

// Show access denied banner if redirected here from requireRole()
$accessError = '';
if (isset($_GET['error']) && $_GET['error'] === 'access_denied') {
    $accessError = 'Access Denied: You do not have permission to view that page.';
}

$role = getUserRole();

// Controller: call the correct Model function based on role
if ($role === 'admin') {
    $data = getAdminDashboardData($pdo);
} elseif ($role === 'receptionist') {
    $data = getReceptionistDashboardData($pdo);
} else {
    $doctorId = $_SESSION['doctor_id'] ?? 0;
    $data = getDoctorDashboardData($pdo, $doctorId);
}

require_once '../includes/header.php'; // Opens the shared page layout
?>

<?php if ($accessError): ?>
<div class="alert alert-danger mb-4">
    <svg data-lucide="shield-off" width="17" height="17"></svg>
    <?= htmlspecialchars($accessError) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Dashboard</h1>
        <div class="page-breadcrumb">
            <svg data-lucide="home" width="12" height="12"></svg>&nbsp;
            Cryptalis Clinic &rsaquo; <?= ucfirst($role) ?> Dashboard
        </div>
    </div>
</div>

<?php if ($role === 'admin'): ?>
<!-- â•â• ADMIN DASHBOARD â•â• -->
<div class="row g-3 mb-3 admin-dashboard-grid">
    <div class="col-lg-3">
        <div class="d-flex flex-column gap-2 admin-kpi-stack">
            <div class="stat-card red flat-panel admin-kpi-cube">
                <div class="stat-icon red"><svg data-lucide="stethoscope"></svg></div>
                <div class="stat-body"><div class="stat-value"><?= $data['totalDoctors'] ?></div><div class="stat-label">Total Doctors</div></div>
            </div>
            <div class="stat-card blue flat-panel admin-kpi-cube">
                <div class="stat-icon blue"><svg data-lucide="users"></svg></div>
                <div class="stat-body"><div class="stat-value"><?= $data['totalPatients'] ?></div><div class="stat-label">Total Patients</div></div>
            </div>
            <div class="stat-card green flat-panel admin-kpi-cube">
                <div class="stat-icon green"><svg data-lucide="calendar-check"></svg></div>
                <div class="stat-body"><div class="stat-value"><?= $data['totalAppointments'] ?></div><div class="stat-label">Total Appointments</div></div>
            </div>
            <div class="stat-card gray flat-panel admin-kpi-cube">
                <div class="stat-icon gray"><svg data-lucide="calendar-clock"></svg></div>
                <div class="stat-body"><div class="stat-value"><?= $data['todayAppointments'] ?></div><div class="stat-label">Appointments Today</div></div>
            </div>
        </div>
    </div>
    <div class="col-lg-9 d-flex flex-column gap-3 admin-right-column">
        <div class="table-card flat-panel">
            <div class="card-header-section">
                <h5><svg data-lucide="calendar-clock" width="17" height="17"></svg>&nbsp;Upcoming Appointments</h5>
                <a href="/clinic_1/public/appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="clinic-table">
                    <thead><tr><th>Patient</th><th>Doctor</th><th>Date &amp; Time</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['recentAppointments'] as $a):
                        $bc = match($a['status']){'Scheduled'=>'scheduled','Completed'=>'completed','Cancelled'=>'cancelled',default=>'scheduled'}; ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($a['patient_name']) ?></strong></td>
                            <td>Dr. <?= htmlspecialchars($a['doctor_name']) ?></td>
                            <td><?= date('M d, Y h:i A', strtotime($a['appointment_datetime'])) ?></td>
                            <td><span class="status-badge <?= $bc ?>"><span class="status-dot"></span><?= $a['status'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($data['recentAppointments'])): ?>
                        <tr><td colspan="4"><div class="empty-state"><svg data-lucide="calendar-x2"></svg><p>No upcoming appointments.</p></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="table-card flat-panel">
            <div class="card-header-section"><h5><svg data-lucide="zap" width="17" height="17"></svg>&nbsp;Quick Actions</h5></div>
            <div class="p-3 p-md-4 d-flex flex-wrap gap-2">
                <a href="/clinic_1/public/appointments.php" class="btn btn-primary"><svg data-lucide="plus-circle"></svg> New Appointment</a>
                <a href="/clinic_1/public/patients.php" class="btn btn-outline-primary"><svg data-lucide="user-plus"></svg> Add Patient</a>
                <a href="/clinic_1/public/doctors.php" class="btn btn-outline-secondary"><svg data-lucide="user-check"></svg> Add Doctor</a>
                <a href="/clinic_1/public/appointments.php" class="btn btn-outline-secondary"><svg data-lucide="calendar"></svg> View Schedule</a>
            </div>
        </div>
    </div>
</div>

<?php elseif ($role === 'receptionist'): ?>
<!-- RECEPTIONIST DASHBOARD -->
<div class="row g-3 mb-3">
    <div class="col-lg-3">
        <div class="d-flex flex-column gap-2">
            <div class="stat-card red flat-panel admin-kpi-cube">
                <div class="stat-icon red"><svg data-lucide="calendar-clock"></svg></div>
                <div class="stat-body"><div class="stat-value"><?= $data['todayAppointments'] ?></div><div class="stat-label">Appointments Today</div></div>
            </div>
            <div class="stat-card blue flat-panel admin-kpi-cube">
                <div class="stat-icon blue"><svg data-lucide="users"></svg></div>
                <div class="stat-body"><div class="stat-value"><?= $data['totalPatients'] ?></div><div class="stat-label">Total Patients</div></div>
            </div>
            <div class="stat-card green flat-panel admin-kpi-cube">
                <div class="stat-icon green"><svg data-lucide="calendar-check"></svg></div>
                <div class="stat-body"><div class="stat-value"><?= $data['upcomingScheduled'] ?></div><div class="stat-label">Upcoming Scheduled</div></div>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="table-card flat-panel">
            <div class="card-header-section">
                <h5><svg data-lucide="sun" width="17" height="17"></svg>&nbsp;Today's Schedule &mdash; <?= date('F j, Y') ?></h5>
                <a href="/clinic_1/public/appointments.php" class="btn btn-sm btn-primary"><svg data-lucide="plus"></svg> Schedule</a>
            </div>
            <div class="table-responsive">
                <table class="clinic-table">
                    <thead><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['todayAppointmentList'] as $a):
                        $bc = match($a['status']){'Scheduled'=>'scheduled','Completed'=>'completed','Cancelled'=>'cancelled',default=>'scheduled'}; ?>
                        <tr>
                            <td><strong><?= date('h:i A', strtotime($a['appointment_datetime'])) ?></strong></td>
                            <td><?= htmlspecialchars($a['patient_name']) ?></td>
                            <td>Dr. <?= htmlspecialchars($a['doctor_name']) ?></td>
                            <td><span class="status-badge <?= $bc ?>"><span class="status-dot"></span><?= $a['status'] ?></span></td>
                            <td><a href="/clinic_1/public/appointments.php?edit=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($data['todayAppointmentList'])): ?>
                        <tr><td colspan="5"><div class="empty-state"><svg data-lucide="calendar-x2"></svg><p>No appointments for today.</p></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="row g-3">
    <div class="col-12">
        <div class="table-card flat-panel">
            <div class="card-header-section"><h5><svg data-lucide="zap" width="17" height="17"></svg>&nbsp;Quick Actions</h5></div>
            <div class="p-3 p-md-4 d-flex flex-wrap gap-2">
                <a href="/clinic_1/public/appointments.php" class="btn btn-primary"><svg data-lucide="plus-circle"></svg> Schedule Appointment</a>
                <a href="/clinic_1/public/patients.php" class="btn btn-outline-primary"><svg data-lucide="user-plus"></svg> Add Patient</a>
                <a href="/clinic_1/public/appointments.php" class="btn btn-outline-secondary"><svg data-lucide="calendar"></svg> View Schedule</a>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- DOCTOR DASHBOARD -->
<div class="row g-3 mb-3">
    <div class="col-lg-3">
        <div class="d-flex flex-column gap-2">
            <div class="stat-card red flat-panel admin-kpi-cube">
                <div class="stat-icon red"><svg data-lucide="calendar-clock"></svg></div>
                <div class="stat-body"><div class="stat-value"><?= $data['myToday'] ?></div><div class="stat-label">My Appointments Today</div></div>
            </div>
            <div class="stat-card blue flat-panel admin-kpi-cube">
                <div class="stat-icon blue"><svg data-lucide="calendar-check"></svg></div>
                <div class="stat-body"><div class="stat-value"><?= $data['myTotal'] ?></div><div class="stat-label">Total Assigned</div></div>
            </div>
            <div class="stat-card green flat-panel admin-kpi-cube">
                <div class="stat-icon green"><svg data-lucide="clock"></svg></div>
                <div class="stat-body"><div class="stat-value"><?= $data['myUpcoming'] ?></div><div class="stat-label">Upcoming Scheduled</div></div>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="table-card flat-panel">
            <div class="card-header-section">
                <h5><svg data-lucide="sun" width="17" height="17"></svg>&nbsp;My Schedule Today &mdash; <?= date('F j, Y') ?></h5>
            </div>
            <div class="table-responsive">
                <table class="clinic-table">
                    <thead><tr><th>Time</th><th>Patient</th><th>Age/Gender</th><th>Notes</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['todayAppts'] as $a):
                        $bc = match($a['status']){'Scheduled'=>'scheduled','Completed'=>'completed','Cancelled'=>'cancelled',default=>'scheduled'}; ?>
                        <tr>
                            <td><strong><?= date('h:i A', strtotime($a['appointment_datetime'])) ?></strong></td>
                            <td><?= htmlspecialchars($a['patient_name']) ?></td>
                            <td><?= $a['age'] ?? '-' ?> y/o, <?= $a['gender'] ?></td>
                            <td style="max-width:280px">
                                <?php $notes = $a['notes'] ?? ''; $nid = 'dn-'.$a['id']; ?>
                                <?php if (!$notes): ?>
                                    <span style="color:var(--text-muted)">-</span>
                                <?php elseif (mb_strlen($notes) <= 60): ?>
                                    <?= nl2br(htmlspecialchars($notes)) ?>
                                <?php else: ?>
                                    <span id="<?= $nid ?>-s">
                                        <?= htmlspecialchars(mb_substr($notes, 0, 60)) ?>...
                                        <button onclick="document.getElementById('<?= $nid ?>-s').style.display='none';document.getElementById('<?= $nid ?>-f').style.display=''"
                                                style="background:none;border:none;color:var(--primary);font-size:.75rem;cursor:pointer;padding:0;margin-left:4px;font-weight:600">Show more</button>
                                    </span>
                                    <span id="<?= $nid ?>-f" style="display:none">
                                        <?= nl2br(htmlspecialchars($notes)) ?>
                                        <button onclick="document.getElementById('<?= $nid ?>-f').style.display='none';document.getElementById('<?= $nid ?>-s').style.display=''"
                                                style="background:none;border:none;color:var(--text-muted);font-size:.75rem;cursor:pointer;padding:0;margin-left:4px;font-weight:600">Show less</button>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="status-badge <?= $bc ?>"><span class="status-dot"></span><?= $a['status'] ?></span></td>
                            <td style="white-space:nowrap">
                                <a href="/clinic_1/public/patient_view.php?id=<?= $a['patient_id'] ?>" class="btn btn-sm btn-outline-info">View Patient</a>
                                <?php if ($a['status'] === 'Scheduled'): ?>
                                <a href="/clinic_1/public/appointment_complete.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary">Complete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($data['todayAppts'])): ?>
                        <tr><td colspan="6"><div class="empty-state"><svg data-lucide="calendar-x2"></svg><p>No appointments scheduled for today.</p></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="row g-3">
    <div class="col-12">
        <div class="table-card flat-panel">
            <div class="card-header-section"><h5><svg data-lucide="zap" width="17" height="17"></svg>&nbsp;Quick Actions</h5></div>
            <div class="p-3 p-md-4 d-flex flex-wrap gap-2">
                <a href="/clinic_1/public/appointments.php" class="btn btn-primary"><svg data-lucide="calendar-check"></svg> My Appointments</a>
                <a href="/clinic_1/public/dashboard.php" class="btn btn-outline-secondary"><svg data-lucide="layout-dashboard"></svg> Refresh Dashboard</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

