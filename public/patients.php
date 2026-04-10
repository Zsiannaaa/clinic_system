<?php
// ============================================================
// public/patients.php — Patient Management (Controller + View)
//
// MVC Role:
//   Model      → modules/patients_module.php
//   Controller → logic at the top of this file
//   View       → HTML below
//
// Access:
//   Admin       — full CRUD
//   Receptionist — Add + Edit (no Delete)
//   Doctor      — blocked by requireRole()
// ============================================================
require_once '../includes/auth.php';
require_once '../modules/patients_module.php';

requireRole(['admin', 'receptionist']);
$role = getUserRole();

// —— ADD ——————————————————————————————————————————————————————
if (isset($_POST['add'])) {
    verifyCsrf('/clinic_1/public/patients.php');
    $err = createPatient(
        $pdo,
        trim($_POST['first_name']     ?? ''),
        trim($_POST['last_name']      ?? ''),
        trim($_POST['gender']         ?? ''),
        trim($_POST['date_of_birth']  ?? ''),
        trim($_POST['contact_number'] ?? ''),
        trim($_POST['address']        ?? '')
    );
    $_SESSION[$err ? 'error' : 'success'] = $err ?? 'Patient added successfully!';
    header('Location: /clinic_1/public/patients.php'); exit();
}

// —— UPDATE ———————————————————————————————————————————————————
if (isset($_POST['update'])) {
    verifyCsrf('/clinic_1/public/patients.php');
    $err = updatePatient(
        $pdo,
        intval($_POST['patient_id']   ?? 0),
        trim($_POST['first_name']     ?? ''),
        trim($_POST['last_name']      ?? ''),
        trim($_POST['gender']         ?? ''),
        trim($_POST['date_of_birth']  ?? ''),
        trim($_POST['contact_number'] ?? ''),
        trim($_POST['address']        ?? '')
    );
    $_SESSION[$err ? 'error' : 'success'] = $err ?? 'Patient updated successfully!';
    header('Location: /clinic_1/public/patients.php'); exit();
}

// —— DELETE (Admin only, POST + CSRF) ———————————————————————
if (isset($_POST['delete_id'])) {
    requireRole(['admin']);
    verifyCsrf('/clinic_1/public/patients.php');
    $err = deletePatient($pdo, intval($_POST['delete_id']));
    $_SESSION[$err ? 'error' : 'success'] = $err ?? 'Patient deleted successfully!';
    header('Location: /clinic_1/public/patients.php'); exit();
}

// —— EDIT MODE ——————————————————————————————————————————————
$edit_patient = null;
if (isset($_GET['edit'])) {
    $edit_patient = getPatientById($pdo, intval($_GET['edit']));
}

// —— FETCH LIST with Pagination ————————————————————————————
$perPage    = 10;
$page       = max(1, intval($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;
$total      = countPatients($pdo);
$totalPages = (int)ceil($total / $perPage);
$patients   = getPatients($pdo, $perPage, $offset);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Patients</h1>
        <div class="page-breadcrumb">Dashboard &rsaquo; Patient Registry</div>
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

<div class="row g-3 patients-layout">
    <div class="col-lg-4 order-lg-2">
        <div class="form-card flat-panel patients-form-card mb-3 mb-lg-0">
            <h5 style="margin-bottom:18px;font-weight:700">
                <svg data-lucide="<?= $edit_patient ? 'pencil' : 'user-plus' ?>" width="17" height="17"></svg>
                &nbsp;<?= $edit_patient ? 'Edit Patient: '.htmlspecialchars($edit_patient['first_name']) : 'Add New Patient' ?>
            </h5>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <?php if ($edit_patient): ?>
                    <input type="hidden" name="patient_id" value="<?= $edit_patient['id'] ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-6 col-lg-12">
                        <label class="form-label">First Name <span style="color:var(--primary)">*</span></label>
                        <input type="text" name="first_name" class="form-control flat-input" required
                               value="<?= htmlspecialchars($edit_patient['first_name'] ?? $_POST['first_name'] ?? '') ?>"
                               placeholder="e.g. Juan">
                    </div>
                    <div class="col-md-6 col-lg-12">
                        <label class="form-label">Last Name <span style="color:var(--primary)">*</span></label>
                        <input type="text" name="last_name" class="form-control flat-input" required
                               value="<?= htmlspecialchars($edit_patient['last_name'] ?? $_POST['last_name'] ?? '') ?>"
                               placeholder="e.g. dela Cruz">
                    </div>
                    <div class="col-md-4 col-lg-12">
                        <label class="form-label">Date of Birth <span style="color:var(--primary)">*</span></label>
                        <input type="date" name="date_of_birth" class="form-control flat-input"
                               max="<?= date('Y-m-d') ?>"
                               value="<?= htmlspecialchars($edit_patient['date_of_birth'] ?? $_POST['date_of_birth'] ?? '') ?>"
                               required>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:3px">Age is computed automatically.</div>
                    </div>
                    <div class="col-md-4 col-lg-12">
                        <label class="form-label">Gender <span style="color:var(--primary)">*</span></label>
                        <select name="gender" class="form-select flat-input" required>
                            <option value="">-- Select --</option>
                            <?php foreach (['Male','Female','Other'] as $g): ?>
                            <option value="<?= $g ?>"
                                <?= (($edit_patient['gender'] ?? $_POST['gender'] ?? '') === $g) ? 'selected' : '' ?>>
                                <?= $g ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 col-lg-12">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control flat-input"
                               value="<?= htmlspecialchars($edit_patient['contact_number'] ?? $_POST['contact_number'] ?? '') ?>"
                               placeholder="e.g. 09171234567">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control flat-input"
                               value="<?= htmlspecialchars($edit_patient['address'] ?? $_POST['address'] ?? '') ?>"
                               placeholder="e.g. 123 Rizal St., Manila">
                    </div>
                </div>
                <div class="d-flex gap-2 mt-4 pt-2 border-top" style="border-color:var(--border)!important">
                    <button type="submit" name="<?= $edit_patient ? 'update' : 'add' ?>" class="btn btn-primary patient-submit-btn">
                        <svg data-lucide="<?= $edit_patient ? 'save' : 'user-plus' ?>"></svg>
                        <?= $edit_patient ? 'Update Patient' : 'Add Patient' ?>
                    </button>
                    <?php if ($edit_patient): ?>
                        <a href="/clinic_1/public/patients.php" class="btn btn-outline-secondary">
                            <svg data-lucide="x"></svg> Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-8 order-lg-1">
        <div class="table-card flat-panel patients-table-card">
            <div class="card-header-section">
                <h5><svg data-lucide="users" width="17" height="17"></svg>&nbsp;Patient Registry</h5>
                <div style="display:flex;align-items:center;gap:10px">
                    <a href="/clinic_1/public/patients_export.php" class="btn btn-sm btn-outline-secondary" title="Export all patients to Excel">
                        <svg data-lucide="save"></svg> Export All
                    </a>
                    <div style="position:relative">
                        <svg data-lucide="search" width="14" height="14" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none"></svg>
                        <input type="text" id="patientSearch" placeholder="Search patients..." oninput="filterTable()"
                               style="padding:6px 10px 6px 30px;border:1.5px solid var(--border);border-radius:0;font-size:.8rem;background:var(--body-bg);color:var(--text-primary);width:200px">
                    </div>
                    <span style="font-size:.78rem;color:var(--text-muted)"><?= $total ?> total &mdash; Page <?= $page ?> of <?= max(1, $totalPages) ?></span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="clinic-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Contact</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="patientTableBody">
                    <?php foreach ($patients as $patient):
                        $dob = $patient['date_of_birth'] ?? null;
                        $age = $dob
                            ? (int)(new DateTime())->diff(new DateTime($dob))->y
                            : ($patient['age'] ?? '—');
                    ?>
                        <tr data-name="<?= strtolower(htmlspecialchars($patient['first_name'].' '.$patient['last_name'])) ?>">
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div style="width:36px;height:36px;border-radius:50%;background:#eaf4fc;color:var(--info);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.83rem;flex-shrink:0">
                                        <?= strtoupper(substr($patient['first_name'], 0, 1)) ?>
                                    </div>
                                    <strong><?= htmlspecialchars($patient['first_name'].' '.$patient['last_name']) ?></strong>
                                </div>
                            </td>
                            <td><?= $age ?> yrs</td>
                            <td><?= htmlspecialchars($patient['gender']) ?></td>
                            <td><?= htmlspecialchars($patient['contact_number'] ?? '') ?></td>
                            <td style="text-align:right;white-space:nowrap">
                                <a href="/clinic_1/public/patient_view.php?id=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-info">
                                    <svg data-lucide="eye"></svg> View
                                </a>
                                <a href="/clinic_1/public/patients_export.php?patient_id=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Export this patient record to Excel">
                                    <svg data-lucide="save"></svg> Export
                                </a>
                                <a href="?edit=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <svg data-lucide="pencil"></svg> Edit
                                </a>
                                <?php if ($role === 'admin'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="delete_id" value="<?= $patient['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <button type="button" class="btn btn-sm btn-outline-danger js-delete-btn"
                                            data-message="Delete patient <?= htmlspecialchars($patient['first_name'].' '.$patient['last_name']) ?>? This cannot be undone.">
                                        <svg data-lucide="trash-2"></svg> Delete
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($patients)): ?>
                        <tr><td colspan="5">
                            <div class="empty-state"><svg data-lucide="users"></svg><p>No patients found.</p></div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="d-flex justify-content-center mt-3">
    <ul class="pagination">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page - 1 ?>">&laquo; Prev</a>
        </li>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page + 1 ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
<script>
function filterTable() {
    const q = document.getElementById('patientSearch').value.toLowerCase();
    document.querySelectorAll('#patientTableBody tr[data-name]').forEach(function(row) {
        row.style.display = row.dataset.name.includes(q) ? '' : 'none';
    });
}
</script>
