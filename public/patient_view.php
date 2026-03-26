<?php
// ============================================================
// public/patient_view.php — View Patient Details (Controller + View)
//
// MVC Role:
//   Model      → modules/patients_module.php
//   Controller → access check + data load at top
//   View       → patient info + appointment history below
//
// Access:
//   Admin/Receptionist — any patient
//   Doctor            — only patients assigned to them via appointments
// ============================================================
require_once '../includes/auth.php';
require_once '../modules/patients_module.php';

requireLogin();
$role = getUserRole();
$id   = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: /clinic_1/public/patients.php'); exit(); }

// Business Rule: Doctor can only view assigned patients
if ($role === 'doctor') {
    $doctorId = $_SESSION['doctor_id'] ?? 0;
    if (!isPatientAssignedToDoctor($pdo, $id, $doctorId)) {
        $_SESSION['error'] = 'Access Denied: You can only view your assigned patients.';
        header('Location: /clinic_1/public/appointments.php'); exit();
    }
} elseif (!in_array($role, ['admin', 'receptionist'])) {
    header('Location: /clinic_1/public/dashboard.php?error=access_denied'); exit();
}

$patient = getPatientById($pdo, $id);
if (!$patient) { header('Location: /clinic_1/public/patients.php'); exit(); }

$appointments = getPatientAppointmentHistory($pdo, $id, $role, $_SESSION['doctor_id'] ?? null);

// Compute age
$age = isset($patient['date_of_birth']) && $patient['date_of_birth']
    ? (int)(new DateTime())->diff(new DateTime($patient['date_of_birth']))->y
    : ($patient['age'] ?? '—');

require_once '../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title"><?= htmlspecialchars($patient['first_name'].' '.$patient['last_name']) ?></h1>
        <div class="page-breadcrumb">
            <a href="<?= $role === 'doctor' ? '/clinic_1/public/appointments.php' : '/clinic_1/public/patients.php' ?>">
                <?= $role === 'doctor' ? 'My Appointments' : 'Patients' ?>
            </a> &rsaquo; Patient Profile
        </div>
    </div>
    <?php if (in_array($role, ['admin', 'receptionist'])): ?>
    <a href="/clinic_1/public/patients.php?edit=<?= $patient['id'] ?>" class="btn btn-outline-primary">
        <svg data-lucide="pencil"></svg> Edit Patient
    </a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <!-- Patient Info Card -->
    <div class="col-md-4">
        <div class="table-card h-100">
            <div class="card-header-section">
                <h5><svg data-lucide="user" width="17" height="17"></svg>&nbsp;Patient Info</h5>
            </div>
            <div class="p-4" style="font-size:.875rem">
                <div style="text-align:center;margin-bottom:20px">
                    <div style="width:64px;height:64px;border-radius:50%;background:#eaf4fc;color:var(--info);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.5rem;margin:0 auto 10px">
                        <?= strtoupper(substr($patient['first_name'], 0, 1)) ?>
                    </div>
                    <div style="font-weight:700;font-size:1.05rem"><?= htmlspecialchars($patient['first_name'].' '.$patient['last_name']) ?></div>
                </div>
                <?php $fields = [
                    ['label'=>'Gender',  'value'=>$patient['gender']],
                    ['label'=>'Age',     'value'=>$age.' years old'],
                    ['label'=>'DOB',     'value'=>isset($patient['date_of_birth']) ? date('F d, Y', strtotime($patient['date_of_birth'])) : '—'],
                    ['label'=>'Contact', 'value'=>$patient['contact_number'] ?? '—'],
                    ['label'=>'Address', 'value'=>$patient['address'] ?? '—'],
                ]; foreach ($fields as $f): ?>
                <div style="margin-bottom:14px">
                    <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:3px"><?= $f['label'] ?></div>
                    <div style="font-weight:600"><?= htmlspecialchars($f['value']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Appointment History -->
    <div class="col-md-8">
        <div class="table-card">
            <div class="card-header-section">
                <h5><svg data-lucide="calendar-clock" width="17" height="17"></svg>&nbsp;Appointment History</h5>
                <span style="font-size:.78rem;color:var(--text-muted)"><?= count($appointments) ?> record(s)</span>
            </div>
            <div class="table-responsive">
                <table class="clinic-table">
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Doctor</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($appointments as $a):
                        $bc = match($a['status']){'Scheduled'=>'scheduled','Completed'=>'completed','Cancelled'=>'cancelled',default=>'scheduled'};
                    ?>
                        <tr>
                            <td><?= date('M d, Y h:i A', strtotime($a['appointment_datetime'])) ?></td>
                            <td>Dr. <?= htmlspecialchars($a['doc_first'].' '.$a['doc_last']) ?></td>
                            <td><?= htmlspecialchars($a['appointment_type'] ?? 'Check-up') ?></td>
                            <td><span class="status-badge <?= $bc ?>"><span class="status-dot"></span><?= $a['status'] ?></span></td>
                            <td>
                                <?php $notes = $a['notes'] ?? ''; $noteId = 'note-'.$a['id']; ?>
                                <?php if (!$notes): ?>
                                    <span style="color:var(--text-muted)">—</span>
                                <?php elseif (mb_strlen($notes) <= 60): ?>
                                    <?= nl2br(htmlspecialchars($notes)) ?>
                                <?php else: ?>
                                    <span id="<?= $noteId ?>-short" style="color:var(--text-secondary)">
                                        <?= htmlspecialchars(mb_substr($notes, 0, 60)) ?>...
                                        <button onclick="document.getElementById('<?= $noteId ?>-short').style.display='none';document.getElementById('<?= $noteId ?>-full').style.display=''"
                                                style="background:none;border:none;color:var(--primary);font-size:.75rem;cursor:pointer;padding:0;margin-left:4px;font-weight:600">Show more</button>
                                    </span>
                                    <span id="<?= $noteId ?>-full" style="display:none">
                                        <?= nl2br(htmlspecialchars($notes)) ?>
                                        <button onclick="document.getElementById('<?= $noteId ?>-full').style.display='none';document.getElementById('<?= $noteId ?>-short').style.display=''"
                                                style="background:none;border:none;color:var(--text-muted);font-size:.75rem;cursor:pointer;padding:0;margin-left:4px;font-weight:600">Show less</button>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($appointments)): ?>
                        <tr><td colspan="5">
                            <div class="empty-state"><svg data-lucide="calendar-x2"></svg><p>No appointment history.</p></div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
