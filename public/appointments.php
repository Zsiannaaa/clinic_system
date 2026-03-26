<?php
// ============================================================
// public/appointments.php — Appointment Management (Controller + View)
//
// MVC Role:
//   Model      → modules/appointments_module.php
//   Controller → logic at the top of this file
//   View       → HTML below
//
// Access:
//   Admin       — full CRUD + status updates
//   Receptionist — Schedule + Edit + Cancel (no Delete)
//   Doctor      — View own appointments only + Complete action
// ============================================================
require_once '../includes/auth.php';
require_once '../modules/appointments_module.php';

requireLogin(); // All 3 roles can access this page
$role     = getUserRole();
$doctorId = $_SESSION['doctor_id'] ?? 0;

// Auto-cancel stale scheduled appointments older than 24 hours.
autoCancelOverdueAppointments($pdo, 24);

// Scheduling form (dropdowns) only needed by admin + receptionist
if (in_array($role, ['admin', 'receptionist'])) {
    require_once '../modules/doctors_module.php';
    require_once '../modules/patients_module.php';
}

// —— ADD (Admin + Receptionist) ———————————————————————————
if (isset($_POST['add'])) {
    requireRole(['admin', 'receptionist']);
    verifyCsrf('/clinic_1/public/appointments.php');
    $err = createAppointment(
        $pdo,
        intval($_POST['doctor_id']  ?? 0),
        intval($_POST['patient_id'] ?? 0),
        trim($_POST['appointment_datetime'] ?? ''),
        trim($_POST['appointment_type']     ?? 'Check-up'),
        trim($_POST['notes']                ?? '')
    );
    $_SESSION[$err ? 'error' : 'success'] = $err ?? 'Appointment scheduled successfully!';
    header('Location: /clinic_1/public/appointments.php'); exit();
}

// —— UPDATE (Admin + Receptionist) ———————————————————————
if (isset($_POST['update'])) {
    requireRole(['admin', 'receptionist']);
    verifyCsrf('/clinic_1/public/appointments.php');
    $requestedStatus = trim($_POST['status'] ?? 'Scheduled');
    if ($role === 'receptionist' && $requestedStatus === 'Completed') {
        $_SESSION['error'] = 'Receptionist is not allowed to mark appointments as Completed.';
        header('Location: /clinic_1/public/appointments.php'); exit();
    }
    $err = updateAppointment(
        $pdo,
        intval($_POST['appt_id']    ?? 0),
        intval($_POST['doctor_id']  ?? 0),
        intval($_POST['patient_id'] ?? 0),
        trim($_POST['appointment_datetime'] ?? ''),
        $requestedStatus,
        trim($_POST['appointment_type']     ?? 'Check-up'),
        trim($_POST['notes']                ?? '')
    );
    $_SESSION[$err ? 'error' : 'success'] = $err ?? 'Appointment updated successfully!';
    header('Location: /clinic_1/public/appointments.php'); exit();
}

// —— DELETE (Admin only, POST + CSRF) ————————————————————
if (isset($_POST['delete_id'])) {
    requireRole(['admin']);
    verifyCsrf('/clinic_1/public/appointments.php');
    $err = deleteAppointment($pdo, intval($_POST['delete_id']));
    $_SESSION[$err ? 'error' : 'success'] = $err ?? 'Appointment deleted successfully!';
    header('Location: /clinic_1/public/appointments.php'); exit();
}

// —— STATUS UPDATE (Doctor own, Admin/Receptionist any) —————————
if (isset($_POST['status_update'])) {
    requireRole(['admin', 'receptionist', 'doctor']);
    verifyCsrf('/clinic_1/public/appointments.php');
    $err = updateAppointmentStatus(
        $pdo,
        intval($_POST['appt_id'] ?? 0),
        trim($_POST['new_status'] ?? ''),
        $role,
        $doctorId
    );
    $_SESSION[$err ? 'error' : 'success'] = $err ?? 'Status updated successfully.';
    header('Location: /clinic_1/public/appointments.php'); exit();
}

// —— EDIT MODE —————————————————————————————————————————————
$edit_appt = null;
if (isset($_GET['edit'])) {
    $editId    = intval($_GET['edit']);
    $edit_appt = getAppointmentById($pdo, $editId);
    // Only admin/receptionist can edit; also only Scheduled appointments
    if ($edit_appt && ($edit_appt['status'] !== 'Scheduled' || !in_array($role, ['admin','receptionist']))) {
        $_SESSION['error'] = $edit_appt['status'] !== 'Scheduled'
            ? 'Only Scheduled appointments can be edited.'
            : 'You do not have permission to edit appointments.';
        $edit_appt = null;
        header('Location: /clinic_1/public/appointments.php'); exit();
    }
}

// Dropdown data for the form
$allDoctors  = in_array($role, ['admin', 'receptionist']) ? getDoctorsForDropdown($pdo) : [];
$allPatients = in_array($role, ['admin', 'receptionist']) ? getPatientsForDropdown($pdo) : [];
$doctorAvailability = !empty($allDoctors)
    ? getDoctorAvailabilityStatuses($pdo, array_column($allDoctors, 'id'), 30)
    : [];

$doctorGroups = ['current_schedule' => [], 'available' => []];
foreach ($allDoctors as $doc) {
    $docStatus = $doctorAvailability[(int)$doc['id']] ?? ['type' => 'available', 'label' => 'Available Now'];
    $statusType = in_array($docStatus['type'], ['busy', 'next'], true) ? 'current_schedule' : 'available';
    $doctorGroups[$statusType][] = ['doc' => $doc];
}

// —— FETCH LIST with Pagination ———————————————————————————
$perPage      = 10;
$page         = max(1, intval($_GET['page'] ?? 1));
$offset       = ($page - 1) * $perPage;
$statusFilter = trim($_GET['status'] ?? 'All');
if (!in_array($statusFilter, ['All','Scheduled','Completed','Cancelled'])) $statusFilter = 'All';

$total        = countAppointments($pdo, $role, $doctorId, $statusFilter);
$totalPages   = (int)ceil($total / $perPage);
$appointments = getAppointments($pdo, $role, $doctorId, $perPage, $offset, $statusFilter);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title"><?= $role === 'doctor' ? 'My Appointments' : 'Appointments' ?></h1>
        <div class="page-breadcrumb">Dashboard &rsaquo; <?= $role === 'doctor' ? 'My Schedule' : 'Appointment Management' ?></div>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success mb-4">
    <svg data-lucide="check-circle" width="17" height="17"></svg>
    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
</div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger mb-4">
    <svg data-lucide="alert-circle" width="17" height="17"></svg>
    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<div class="appointments-layout">
<?php if (in_array($role, ['admin', 'receptionist'])): ?><div class="row g-3"><?php endif; ?>

<?php if (in_array($role, ['admin', 'receptionist'])): ?><div class="col-lg-8"><?php endif; ?>
<div class="table-card flat-panel appointments-table-card mb-3 mb-lg-0">
    <div class="card-header-section">
        <h5><svg data-lucide="calendar-check" width="17" height="17"></svg>&nbsp;Appointment Records</h5>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end">
            <div class="appointments-filter-tabs">
                <?php foreach (['All','Scheduled','Completed','Cancelled'] as $tab): ?>
                <a href="?status=<?= $tab ?>" class="btn btn-sm <?= $statusFilter === $tab ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $tab ?></a>
                <?php endforeach; ?>
            </div>
            <span style="font-size:.78rem;color:var(--text-muted)">
                <?= $total ?> total &mdash; Page <?= $page ?> of <?= max(1, $totalPages) ?>
            </span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="clinic-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Date &amp; Time</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($appointments as $a):
                $bc = match($a['status']){'Scheduled'=>'scheduled','Completed'=>'completed','Cancelled'=>'cancelled',default=>'scheduled'};
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['pat_first'].' '.$a['pat_last']) ?></strong></td>
                    <td>Dr. <?= htmlspecialchars($a['doc_first'].' '.$a['doc_last']) ?></td>
                    <td><?= date('M d, Y h:i A', strtotime($a['appointment_datetime'])) ?></td>
                    <td><?= htmlspecialchars($a['appointment_type'] ?? 'Check-up') ?></td>
                    <td><span class="status-badge <?= $bc ?>"><span class="status-dot"></span><?= $a['status'] ?></span></td>
                    <td style="text-align:right;white-space:nowrap">

                        <?php if ($role === 'doctor'): ?>
                            <a href="/clinic_1/public/patient_view.php?id=<?= $a['patient_id'] ?>" class="btn btn-sm btn-outline-info">
                                <svg data-lucide="eye"></svg> Patient
                            </a>
                            <?php if ($a['status'] === 'Scheduled'): ?>
                            <a href="/clinic_1/public/appointment_complete.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                <svg data-lucide="check-circle"></svg> Complete
                            </a>
                            <?php endif; ?>

                        <?php else: ?>
                            <?php if ($a['status'] === 'Scheduled'): ?>
                            <a href="?edit=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <svg data-lucide="pencil"></svg> Edit
                            </a>
                            <?php endif; ?>
                            <?php if ($role === 'admin' && $a['status'] === 'Scheduled'): ?>
                            <a href="/clinic_1/public/appointment_complete.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-success">
                                <svg data-lucide="check-circle"></svg> Complete
                            </a>
                            <?php endif; ?>
                            <?php if ($a['status'] === 'Scheduled'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="appt_id" value="<?= $a['id'] ?>">
                                <input type="hidden" name="new_status" value="Cancelled">
                                <input type="hidden" name="status_update" value="1">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <button type="button" class="btn btn-sm btn-outline-warning js-delete-btn"
                                        data-message="Cancel this appointment?">
                                    <svg data-lucide="x-circle"></svg> Cancel
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php
                                $canDelete = ($role === 'admin')
                                    && (($a['status'] ?? '') === 'Scheduled')
                                    && (strtotime($a['appointment_datetime']) > time());
                            ?>
                            <?php if ($canDelete): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="delete_id" value="<?= $a['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <button type="button" class="btn btn-sm btn-outline-danger js-delete-btn"
                                        data-message="Permanently delete appointment #<?= $a['id'] ?>? This cannot be undone.">
                                    <svg data-lucide="trash-2"></svg> Delete
                                </button>
                            </form>
                            <?php endif; ?>
                        <?php endif; ?>

                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($appointments)): ?>
                <tr><td colspan="6">
                    <div class="empty-state"><svg data-lucide="calendar-x2"></svg><p>No appointments found.</p></div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if (in_array($role, ['admin', 'receptionist'])): ?></div><?php endif; ?>

<?php if (in_array($role, ['admin', 'receptionist'])): ?>
<div class="col-lg-4">
    <div class="form-card flat-panel appointments-form-card">
        <h5 style="margin-bottom:18px;font-weight:700">
            <svg data-lucide="<?= $edit_appt ? 'pencil' : 'calendar-plus' ?>" width="17" height="17"></svg>
            &nbsp;<?= $edit_appt ? 'Edit Appointment #'.$edit_appt['id'] : 'Schedule Appointment' ?>
        </h5>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <?php if ($edit_appt): ?>
                <input type="hidden" name="appt_id" value="<?= $edit_appt['id'] ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Doctor <span style="color:var(--primary)">*</span></label>
                    <select name="doctor_id" class="form-select flat-input" required>
                        <option value="">-- Select Doctor --</option>
                        <?php
                        $groupLabels = [
                            'current_schedule' => 'Current Schedule',
                            'available' => 'Available Now',
                        ];
                        foreach (['current_schedule', 'available'] as $groupKey):
                            if (empty($doctorGroups[$groupKey])) continue;
                        ?>
                        <optgroup label="<?= $groupLabels[$groupKey] ?>">
                            <?php foreach ($doctorGroups[$groupKey] as $row):
                                $doc = $row['doc'];
                            ?>
                            <option value="<?= $doc['id'] ?>"
                                <?= (($edit_appt['doctor_id'] ?? 0) == $doc['id']) ? 'selected' : '' ?>>
                                Dr. <?= htmlspecialchars($doc['first_name'].' '.$doc['last_name']) ?> &mdash; <?= htmlspecialchars($doc['specialization']) ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Patient <span style="color:var(--primary)">*</span></label>
                    <select name="patient_id" class="form-select flat-input" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($allPatients as $pat): ?>
                        <option value="<?= $pat['id'] ?>"
                            <?= (($edit_appt['patient_id'] ?? 0) == $pat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pat['first_name'].' '.$pat['last_name']) ?>
                            (<?= $pat['gender'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Date &amp; Time <span style="color:var(--primary)">*</span></label>
                    <input type="datetime-local" name="appointment_datetime" class="form-control flat-input" required
                           min="<?= date('Y-m-d\TH:i') ?>"
                           value="<?= $edit_appt ? date('Y-m-d\TH:i', strtotime($edit_appt['appointment_datetime'])) : '' ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Type</label>
                    <select name="appointment_type" class="form-select flat-input">
                        <?php foreach (['Check-up','Follow-up','Consultation','Vaccination','Lab Result Review','Other'] as $t): ?>
                        <option value="<?= $t ?>" <?= (($edit_appt['appointment_type'] ?? 'Check-up') === $t) ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($edit_appt): ?>
                <div class="col-12">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select flat-input">
                        <?php
                            $statusChoices = $role === 'receptionist'
                                ? ['Scheduled','Cancelled']
                                : ['Scheduled','Completed','Cancelled'];
                            foreach ($statusChoices as $st):
                        ?>
                        <option value="<?= $st ?>" <?= $edit_appt['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control flat-input" rows="2"
                              placeholder="Optional notes..."><?= htmlspecialchars($edit_appt['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4 pt-2 border-top" style="border-color:var(--border)!important">
                <button type="submit" name="<?= $edit_appt ? 'update' : 'add' ?>" class="btn btn-primary appt-submit-btn">
                    <svg data-lucide="<?= $edit_appt ? 'save' : 'calendar-plus' ?>"></svg>
                    <?= $edit_appt ? 'Update Appointment' : 'Schedule Appointment' ?>
                </button>
                <?php if ($edit_appt): ?>
                    <a href="/clinic_1/public/appointments.php" class="btn btn-outline-secondary">
                        <svg data-lucide="x"></svg> Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
</div>
<?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
<nav class="d-flex justify-content-center mt-3">
    <ul class="pagination">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?status=<?= $statusFilter ?>&page=<?= $page - 1 ?>">&laquo; Prev</a>
        </li>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="?status=<?= $statusFilter ?>&page=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?status=<?= $statusFilter ?>&page=<?= $page + 1 ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
