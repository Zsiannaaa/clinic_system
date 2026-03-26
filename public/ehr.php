<?php
require_once '../includes/auth.php';
require_once '../modules/ehr_module.php';

requireRole(['admin', 'receptionist', 'doctor']);

$role = getUserRole();
$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);

if (isset($_POST['upload_attachment'])) {
    verifyCsrf('/clinic_1/public/ehr.php');
    $noteId = (int)($_POST['note_id'] ?? 0);
    $err = createEhrAttachment($pdo, $noteId, $_FILES['attachment'] ?? [], $userId, $role, $doctorId);
    $patientRedirect = (int)($_POST['patient_id'] ?? 0);
    $_SESSION[$err ? 'error' : 'success'] = $err ?? 'Attachment uploaded.';
    header('Location: /clinic_1/public/ehr.php?patient_id=' . $patientRedirect);
    exit();
}

if (isset($_POST['add_note'])) {
    verifyCsrf('/clinic_1/public/ehr.php');
    $err = createEhrNote(
        $pdo,
        (int)($_POST['patient_id'] ?? 0),
        $role === 'doctor' ? $doctorId : ((int)($_POST['doctor_id'] ?? 0) ?: null),
        ((int)($_POST['appointment_id'] ?? 0) ?: null),
        $userId,
        $role,
        trim($_POST['note_type'] ?? 'General'),
        trim($_POST['note_text'] ?? '')
    );
    $_SESSION[$err ? 'error' : 'success'] = $err ?? 'EHR note saved.';
    $redirectPatient = (int)($_POST['patient_id'] ?? 0);
    header('Location: /clinic_1/public/ehr.php?patient_id=' . $redirectPatient);
    exit();
}

$patients = getEhrPatientsForRole($pdo, $role, $doctorId);
$selectedPatientId = max(0, (int)($_GET['patient_id'] ?? ($_POST['patient_id'] ?? 0)));
if ($selectedPatientId === 0 && !empty($patients)) {
    $selectedPatientId = (int)$patients[0]['id'];
}

$notes = $selectedPatientId ? getEhrNotes($pdo, $selectedPatientId, $role, $doctorId) : [];
$attachmentsByNote = getEhrAttachmentsForNotes($pdo, array_column($notes, 'id'));

require_once '../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">EHR Notes</h1>
        <div class="page-breadcrumb">Dashboard &rsaquo; Electronic Health Records</div>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success mb-4">
    <svg data-lucide="check-circle" width="17" height="17"></svg>
    <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
</div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger mb-4">
    <svg data-lucide="alert-circle" width="17" height="17"></svg>
    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="table-card flat-panel">
            <div class="card-header-section">
                <h5><svg data-lucide="file-text" width="17" height="17"></svg>&nbsp;Patient Chart Notes</h5>
                <form method="GET" style="display:flex;gap:8px;align-items:center;min-width:300px">
                    <select name="patient_id" class="form-select flat-input" onchange="this.form.submit()">
                        <?php foreach ($patients as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $selectedPatientId === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['last_name'] . ', ' . $p['first_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="table-responsive">
                <table class="clinic-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Author</th>
                            <th>Details</th>
                            <th>Files</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notes as $n): ?>
                        <tr>
                            <td><?= date('M d, Y h:i A', strtotime($n['created_at'])) ?></td>
                            <td><?= htmlspecialchars($n['note_type']) ?></td>
                            <td><?= htmlspecialchars($n['author_name']) ?></td>
                            <td><?= nl2br(htmlspecialchars($n['note_text'])) ?></td>
                            <td>
                                <?php $files = $attachmentsByNote[(int)$n['id']] ?? []; ?>
                                <?php if (empty($files)): ?>
                                    <span style="color:var(--text-muted)">No files</span>
                                <?php else: ?>
                                    <div style="display:flex;flex-direction:column;gap:4px">
                                        <?php foreach ($files as $f): ?>
                                        <a href="/clinic_1/public/ehr_attachment.php?id=<?= (int)$f['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                            <svg data-lucide="paperclip"></svg> <?= htmlspecialchars($f['original_name']) ?>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (in_array($role, ['admin', 'doctor'], true)): ?>
                                <form method="POST" enctype="multipart/form-data" style="margin-top:6px;display:flex;gap:6px;align-items:center">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>">
                                    <input type="hidden" name="patient_id" value="<?= (int)$selectedPatientId ?>">
                                    <input type="file" name="attachment" required style="max-width:170px;font-size:.78rem">
                                    <button type="submit" name="upload_attachment" value="1" class="btn btn-sm btn-outline-primary">
                                        Upload
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($notes)): ?>
                        <tr><td colspan="5"><div class="empty-state"><svg data-lucide="file-text"></svg><p>No EHR notes yet.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="form-card flat-panel">
            <h5 style="margin-bottom:18px;font-weight:700">
                <svg data-lucide="plus-circle" width="17" height="17"></svg>&nbsp;Add Note
            </h5>
            <?php if (in_array($role, ['admin', 'doctor'], true)): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="mb-3">
                    <label class="form-label">Patient</label>
                    <select name="patient_id" class="form-select flat-input" required>
                        <?php foreach ($patients as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $selectedPatientId === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['last_name'] . ', ' . $p['first_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Note Type</label>
                    <select name="note_type" class="form-select flat-input" required>
                        <?php foreach (['Consultation','Diagnosis','Prescription','Follow-up','General'] as $t): ?>
                        <option value="<?= $t ?>"><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Appointment ID (optional)</label>
                    <input type="number" name="appointment_id" min="1" class="form-control flat-input" placeholder="e.g. 24">
                </div>
                <div class="mb-3">
                    <label class="form-label">Clinical Note</label>
                    <textarea name="note_text" rows="7" class="form-control flat-input" required placeholder="Write assessment, diagnosis, plan..."></textarea>
                </div>
                <button class="btn btn-primary" name="add_note" value="1">
                    <svg data-lucide="save"></svg> Save EHR Note
                </button>
            </form>
            <?php else: ?>
            <div class="alert alert-warning mb-0">
                <svg data-lucide="info" width="16" height="16"></svg>
                Receptionist has read-only access to EHR notes.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
