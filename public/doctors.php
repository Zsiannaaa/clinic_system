<?php
// ============================================================
// public/doctors.php - Doctor Management (Controller + View)
//
// MVC Role:
//   Model      -> modules/doctors_module.php
//   Controller -> logic at the top of this file (handles POST/GET actions)
//   View       -> HTML below (form + table)
//
// Access:
//   Admin        -> full CRUD (add, edit, delete)
//   Receptionist -> read-only list (no add/edit/delete buttons)
//   Doctor       -> blocked by requireRole()
// ============================================================
require_once '../includes/auth.php';
require_once '../modules/doctors_module.php';

requireRole(['admin', 'receptionist']); // Doctor role is redirected
$role = getUserRole();

// -- ADD (Admin only) ----------------------------------------
if (isset($_POST['add'])) {
    requireRole(['admin']);
    verifyCsrf('/clinic_1/public/doctors.php');
    $err = createDoctor(
        $pdo,
        trim($_POST['first_name']  ?? ''),
        trim($_POST['last_name']   ?? ''),
        trim($_POST['specialization'] ?? ''),
        trim($_POST['contact_number'] ?? '')
    );
    $_SESSION[$err ? 'error' : 'success'] = $err ?? 'Doctor added successfully!';
    header('Location: /clinic_1/public/doctors.php'); exit();
}

// -- UPDATE (Admin only) -------------------------------------
if (isset($_POST['update'])) {
    requireRole(['admin']);
    verifyCsrf('/clinic_1/public/doctors.php');
    $err = updateDoctor(
        $pdo,
        intval($_POST['doctor_id'] ?? 0),
        trim($_POST['first_name']     ?? ''),
        trim($_POST['last_name']      ?? ''),
        trim($_POST['specialization'] ?? ''),
        trim($_POST['contact_number'] ?? '')
    );
    $_SESSION[$err ? 'error' : 'success'] = $err ?? 'Doctor updated successfully!';
    header('Location: /clinic_1/public/doctors.php'); exit();
}

// -- DELETE (Admin only, POST + CSRF) ------------------------
if (isset($_POST['delete_id'])) {
    requireRole(['admin']);
    verifyCsrf('/clinic_1/public/doctors.php');
    $err = deleteDoctor($pdo, intval($_POST['delete_id']));
    $_SESSION[$err ? 'error' : 'success'] = $err ?? 'Doctor deleted successfully!';
    header('Location: /clinic_1/public/doctors.php'); exit();
}

// -- EDIT MODE: load doctor for pre-filled form --------------
$edit_doctor = null;
if (isset($_GET['edit']) && $role === 'admin') {
    $edit_doctor = getDoctorById($pdo, intval($_GET['edit']));
}

// -- FETCH LIST with Pagination -------------------------------
$perPage    = 10;
$page       = max(1, intval($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;
$total      = countDoctors($pdo);
$totalPages = (int)ceil($total / $perPage);
$doctors    = getDoctors($pdo, $perPage, $offset);
$statusMap  = getDoctorAvailabilityStatuses($pdo, array_column($doctors, 'id'), 30);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Doctors</h1>
        <div class="page-breadcrumb">Dashboard &rsaquo; Medical Staff</div>
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

<div class="table-card flat-panel doctors-directory">
    <div class="card-header-section">
        <h5><svg data-lucide="stethoscope" width="17" height="17"></svg>&nbsp;Medical Staff Directory</h5>
        <div style="display:flex;align-items:center;gap:10px">
            <div class="view-toggle" role="group" aria-label="Doctors view mode">
                <button type="button" class="btn btn-sm btn-outline-secondary is-active" id="doctorCardViewBtn">
                    <svg data-lucide="layout-dashboard"></svg> Cards
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="doctorTableViewBtn">
                    <svg data-lucide="list"></svg> Table
                </button>
            </div>
            <div style="position:relative">
                <svg data-lucide="search" width="14" height="14" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none"></svg>
                <input type="text" id="doctorSearch" placeholder="Search doctors..." oninput="filterDoctorsView()"
                       style="padding:6px 10px 6px 30px;border:1.5px solid var(--border);border-radius:var(--radius-md);font-size:.8rem;background:var(--body-bg);color:var(--text-primary);width:220px">
            </div>
            <span style="font-size:.78rem;color:var(--text-muted)"><?= $total ?> total &mdash; Page <?= $page ?> of <?= max(1, $totalPages) ?></span>
        </div>
    </div>
    <div class="doctors-card-view" id="doctorsCardView">
        <div class="doctors-card-grid" id="doctorCardGrid">
            <?php foreach ($doctors as $doc):
                $docId = (int)$doc['id'];
                $status = $statusMap[$docId] ?? ['type' => 'available', 'label' => 'Available Now'];
                $statusColor = match ($status['type']) {
                    'busy' => 'var(--danger)',
                    'next' => '#c2410c',
                    default => '#15803d',
                };
                $searchText = strtolower(trim(($doc['first_name'] ?? '') . ' ' . ($doc['last_name'] ?? '') . ' ' . ($doc['specialization'] ?? '')));
            ?>
            <article class="doctor-card" data-search="<?= htmlspecialchars($searchText) ?>">
                <div class="doctor-card-head">
                    <div class="doctor-avatar"><?= strtoupper(substr($doc['first_name'], 0, 1)) ?></div>
                    <div>
                        <h6 class="doctor-name">Dr. <?= htmlspecialchars($doc['first_name'].' '.$doc['last_name']) ?></h6>
                        <div class="doctor-meta"><?= htmlspecialchars($doc['specialization']) ?></div>
                    </div>
                </div>
                <div class="doctor-card-body">
                    <div class="doctor-line">
                        <span class="doctor-label">Status</span>
                        <span class="doctor-status-text" style="color:<?= $statusColor ?>">
                            <span class="doctor-status-dot"></span><?= htmlspecialchars($status['label']) ?>
                        </span>
                    </div>
                    <div class="doctor-line">
                        <span class="doctor-label">Contact</span>
                        <span class="doctor-value"><?= htmlspecialchars($doc['contact_number'] ?? '-') ?></span>
                    </div>
                </div>
                <?php if ($role === 'admin'): ?>
                <div class="doctor-card-actions">
                    <a href="?edit=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-primary">
                        <svg data-lucide="pencil"></svg> Edit
                    </a>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="delete_id" value="<?= $doc['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <button type="button" class="btn btn-sm btn-outline-danger js-delete-btn"
                                data-message="Delete Dr. <?= htmlspecialchars($doc['first_name'].' '.$doc['last_name']) ?>? This cannot be undone.">
                            <svg data-lucide="trash-2"></svg> Delete
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <?php if (empty($doctors)): ?>
        <div class="empty-state">
            <svg data-lucide="stethoscope"></svg>
            <p>No doctors found. Add a doctor below.</p>
        </div>
        <?php endif; ?>
    </div>

    <div class="table-responsive doctors-table-view" id="doctorsTableView" style="display:none">
        <table class="clinic-table">
            <thead>
                <tr>
                    <th>Doctor</th>
                    <th>Specialization</th>
                    <th>Status</th>
                    <th>Contact</th>
                    <?php if ($role === 'admin'): ?><th style="text-align:right">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="doctorTableBody">
            <?php foreach ($doctors as $doc):
                $docId = (int)$doc['id'];
                $status = $statusMap[$docId] ?? ['type' => 'available', 'label' => 'Available Now'];
                $statusColor = match ($status['type']) {
                    'busy' => 'var(--danger)',
                    'next' => '#c2410c',
                    default => '#15803d',
                };
                $searchText = strtolower(trim(($doc['first_name'] ?? '') . ' ' . ($doc['last_name'] ?? '') . ' ' . ($doc['specialization'] ?? '')));
            ?>
                <tr data-search="<?= htmlspecialchars($searchText) ?>">
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:36px;height:36px;border-radius:50%;background:var(--primary-pale);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.83rem;flex-shrink:0">
                                <?= strtoupper(substr($doc['first_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <strong>Dr. <?= htmlspecialchars($doc['first_name'].' '.$doc['last_name']) ?></strong>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($doc['specialization']) ?></td>
                    <td>
                        <span class="doctor-status-text" style="color:<?= $statusColor ?>">
                            <span class="doctor-status-dot"></span><?= htmlspecialchars($status['label']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($doc['contact_number'] ?? '-') ?></td>
                    <?php if ($role === 'admin'): ?>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="?edit=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <svg data-lucide="pencil"></svg> Edit
                        </a>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="delete_id" value="<?= $doc['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <button type="button" class="btn btn-sm btn-outline-danger js-delete-btn"
                                    data-message="Delete Dr. <?= htmlspecialchars($doc['first_name'].' '.$doc['last_name']) ?>? This cannot be undone.">
                                <svg data-lucide="trash-2"></svg> Delete
                            </button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($doctors)): ?>
                <tr><td colspan="<?= $role === 'admin' ? 5 : 4 ?>">
                    <div class="empty-state">
                        <svg data-lucide="stethoscope"></svg>
                        <p>No doctors found. Add a doctor below.</p>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
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

<?php if ($role === 'admin'): ?>
<section class="doctor-form-strip mt-4">
    <div class="doctor-form-strip-head">
        <h5>
            <svg data-lucide="<?= $edit_doctor ? 'pencil' : 'user-plus' ?>" width="17" height="17"></svg>
            &nbsp;<?= $edit_doctor ? 'Edit Doctor Profile' : 'Add New Doctor' ?>
        </h5>
        <?php if ($edit_doctor): ?>
            <a href="/clinic_1/public/doctors.php" class="btn btn-sm btn-outline-secondary">
                <svg data-lucide="x"></svg> Cancel Edit
            </a>
        <?php endif; ?>
    </div>

    <form method="POST" class="doctor-form-strip-body">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <?php if ($edit_doctor): ?>
            <input type="hidden" name="doctor_id" value="<?= $edit_doctor['id'] ?>">
        <?php endif; ?>

        <div class="doctor-form-grid">
            <div>
                <label class="form-label">First Name <span style="color:var(--primary)">*</span></label>
                <input type="text" name="first_name" class="form-control flat-input" required
                       value="<?= htmlspecialchars($edit_doctor['first_name'] ?? $_POST['first_name'] ?? '') ?>"
                       placeholder="e.g. James">
            </div>
            <div>
                <label class="form-label">Last Name <span style="color:var(--primary)">*</span></label>
                <input type="text" name="last_name" class="form-control flat-input" required
                       value="<?= htmlspecialchars($edit_doctor['last_name'] ?? $_POST['last_name'] ?? '') ?>"
                       placeholder="e.g. Santos">
            </div>
            <div>
                <label class="form-label">Specialization <span style="color:var(--primary)">*</span></label>
                <input type="text" name="specialization" class="form-control flat-input" required
                       value="<?= htmlspecialchars($edit_doctor['specialization'] ?? $_POST['specialization'] ?? '') ?>"
                       placeholder="e.g. General Practice, Cardiology">
            </div>
            <div>
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact_number" class="form-control flat-input"
                       value="<?= htmlspecialchars($edit_doctor['contact_number'] ?? $_POST['contact_number'] ?? '') ?>"
                       placeholder="e.g. 09171234567">
            </div>
        </div>

        <div class="doctor-form-strip-actions">
            <button type="submit" name="<?= $edit_doctor ? 'update' : 'add' ?>" class="btn btn-primary">
                <svg data-lucide="<?= $edit_doctor ? 'save' : 'plus-circle' ?>"></svg>
                <?= $edit_doctor ? 'Update Doctor' : 'Add Doctor' ?>
            </button>
        </div>
    </form>
</section>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
<script>
function filterDoctorsView() {
    const q = (document.getElementById('doctorSearch')?.value || '').toLowerCase().trim();
    document.querySelectorAll('#doctorTableBody tr[data-search]').forEach(function (row) {
        const haystack = row.dataset.search || '';
        row.style.display = haystack.includes(q) ? '' : 'none';
    });
    document.querySelectorAll('#doctorCardGrid .doctor-card[data-search]').forEach(function (card) {
        const haystack = card.dataset.search || '';
        card.style.display = haystack.includes(q) ? '' : 'none';
    });
}

(function initDoctorsViewToggle() {
    const cardBtn = document.getElementById('doctorCardViewBtn');
    const tableBtn = document.getElementById('doctorTableViewBtn');
    const cardView = document.getElementById('doctorsCardView');
    const tableView = document.getElementById('doctorsTableView');
    if (!cardBtn || !tableBtn || !cardView || !tableView) return;

    function setView(view) {
        const cardMode = view === 'card';
        cardView.style.display = cardMode ? '' : 'none';
        tableView.style.display = cardMode ? 'none' : '';
        cardBtn.classList.toggle('is-active', cardMode);
        tableBtn.classList.toggle('is-active', !cardMode);
    }

    cardBtn.addEventListener('click', function () { setView('card'); });
    tableBtn.addEventListener('click', function () { setView('table'); });
    setView('card'); // default
})();
</script>
