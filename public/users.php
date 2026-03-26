<?php
// ============================================================
// public/users.php — User Account Management (Controller + View)
//
// MVC Role:
//   Model      → modules/users_module.php
//   Controller → logic at the top of this file
//   View       → HTML below
//
// Access: Admin only — requireRole(['admin'])
// ============================================================
require_once '../includes/auth.php';
require_once '../modules/users_module.php';

requireRole(['admin']); // Only admin manages user accounts
$currentUserId = $_SESSION['user_id'];

// —— ADD ——————————————————————————————————————————————————————
if (isset($_POST['add'])) {
    verifyCsrf('/clinic_1/public/users.php');
    $errors = createUser(
        $pdo,
        trim($_POST['full_name']  ?? ''),
        trim($_POST['username']   ?? ''),
        $_POST['password']  ?? '',
        $_POST['password2'] ?? '',
        trim($_POST['role'] ?? ''),
        !empty($_POST['doctor_id']) ? intval($_POST['doctor_id']) : null
    );
    if ($errors) { $_SESSION['error'] = implode(' ', $errors); }
    else { $_SESSION['success'] = 'User account created successfully!'; }
    header('Location: /clinic_1/public/users.php'); exit();
}

// —— UPDATE ———————————————————————————————————————————————————
if (isset($_POST['update'])) {
    verifyCsrf('/clinic_1/public/users.php');
    $errors = updateUser(
        $pdo,
        intval($_POST['user_id']  ?? 0),
        trim($_POST['full_name']  ?? ''),
        trim($_POST['username']   ?? ''),
        $_POST['password']  ?? '',
        $_POST['password2'] ?? '',
        trim($_POST['role'] ?? ''),
        $currentUserId
    );
    if ($errors) { $_SESSION['error'] = implode(' ', $errors); }
    else { $_SESSION['success'] = 'User account updated successfully!'; }
    header('Location: /clinic_1/public/users.php'); exit();
}

// —— DELETE (POST + CSRF) ————————————————————————————————
if (isset($_POST['delete_id'])) {
    verifyCsrf('/clinic_1/public/users.php');
    $err = deleteUser($pdo, intval($_POST['delete_id']), $currentUserId);
    $_SESSION[$err ? 'error' : 'success'] = $err ?? 'User account deleted successfully!';
    header('Location: /clinic_1/public/users.php'); exit();
}

// —— EDIT MODE ——————————————————————————————————————————————
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_user = getUserById($pdo, intval($_GET['edit']));
}
$isSelfEdit = $edit_user && ((int)$edit_user['id'] === (int)$currentUserId);

$users            = getUsers($pdo);
$unlinkedDoctors  = getUnlinkedDoctors($pdo);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">System Users</h1>
        <div class="page-breadcrumb">Dashboard &rsaquo; User Management</div>
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

<div class="row g-3 users-layout">
    <div class="col-lg-9">
        <div class="table-card flat-panel users-table-card">
            <div class="card-header-section">
                <h5><svg data-lucide="shield" width="17" height="17"></svg>&nbsp;User Accounts</h5>
                <span style="font-size:.78rem;color:var(--text-muted)"><?= count($users) ?> total</span>
            </div>
            <div class="table-responsive">
                <table class="clinic-table">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Linked Doctor</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u):
                        $roleColors = [
                            'admin'        => 'background:var(--danger-pale);color:var(--danger)',
                            'receptionist' => 'background:var(--info-pale);color:var(--info)',
                            'doctor'       => 'background:#f0fdf4;color:#16a34a',
                        ];
                        $rc = $roleColors[$u['role']] ?? 'background:#f1f5f9;color:#64748b';
                    ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div style="width:36px;height:36px;border-radius:50%;background:var(--primary-pale);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.83rem;flex-shrink:0">
                                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                    </div>
                                    <strong><?= htmlspecialchars($u['full_name']) ?></strong>
                                    <?php if ($u['id'] == $currentUserId): ?>
                                        <span style="font-size:.7rem;background:var(--info-pale);color:var(--info);padding:2px 8px;border-radius:20px;font-weight:600">You</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="font-family:monospace;font-size:.84rem"><?= htmlspecialchars($u['username']) ?></td>
                            <td>
                                <span style="font-size:.75rem;font-weight:600;padding:3px 10px;border-radius:20px;<?= $rc ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td>
                                <?= ($u['doc_first'] ?? false)
                                    ? 'Dr. '.htmlspecialchars($u['doc_first'].' '.$u['doc_last'])
                                    : '<span style="color:var(--text-muted);font-size:.8rem">—</span>' ?>
                            </td>
                            <td style="text-align:right">
                                <div class="users-actions">
                                    <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <svg data-lucide="pencil"></svg> Edit
                                    </a>
                                    <?php if ($u['id'] != $currentUserId): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <button type="button" class="btn btn-sm btn-outline-danger js-delete-btn"
                                                data-message="Delete account for <?= htmlspecialchars($u['full_name']) ?>?">
                                            <svg data-lucide="trash-2"></svg> Delete
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="form-card flat-panel users-form-card">
            <h5 style="margin-bottom:18px;font-weight:700">
                <svg data-lucide="<?= $edit_user ? 'pencil' : 'user-plus' ?>" width="17" height="17"></svg>
                &nbsp;<?= $edit_user ? 'Edit User: '.htmlspecialchars($edit_user['full_name']) : 'Add User Account' ?>
            </h5>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <?php if ($edit_user): ?>
                    <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Full Name <span style="color:var(--primary)">*</span></label>
                        <input type="text" name="full_name" class="form-control flat-input" required
                               value="<?= htmlspecialchars($edit_user['full_name'] ?? '') ?>"
                               placeholder="e.g. Maria Santos">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Username <span style="color:var(--primary)">*</span></label>
                        <input type="text" name="username" class="form-control flat-input" required
                               value="<?= htmlspecialchars($edit_user['username'] ?? '') ?>"
                               placeholder="e.g. maria_santos" autocomplete="off">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Role <span style="color:var(--primary)">*</span></label>
                        <?php if ($isSelfEdit): ?>
                        <input type="hidden" name="role" value="<?= htmlspecialchars($edit_user['role']) ?>">
                        <select class="form-select flat-input" disabled>
                            <option><?= ucfirst($edit_user['role']) ?> (locked for your account)</option>
                        </select>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:4px">
                            Your own role cannot be changed to prevent admin lockout.
                        </div>
                        <?php else: ?>
                        <select name="role" class="form-select flat-input" required id="roleSelect" onchange="toggleDoctorField()">
                            <option value="">-- Select Role --</option>
                            <?php foreach (['admin','receptionist','doctor'] as $r): ?>
                            <option value="<?= $r ?>" <?= (($edit_user['role'] ?? '') === $r) ? 'selected' : '' ?>>
                                <?= ucfirst($r) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Password <?= $edit_user ? '<span style="font-weight:400;color:var(--text-muted)">(leave blank to keep)</span>' : '<span style="color:var(--primary)">*</span>' ?></label>
                        <input type="password" name="password" class="form-control flat-input" autocomplete="new-password"
                               <?= $edit_user ? '' : 'required' ?> placeholder="Min. 6 characters">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="password2" class="form-control flat-input" autocomplete="new-password" placeholder="Repeat password">
                    </div>
                    <?php if (!$edit_user): ?>
                    <div class="col-12" id="doctorField" style="display:none">
                        <label class="form-label">Link to Doctor Profile <span style="color:var(--primary)">*</span></label>
                        <?php if (!empty($unlinkedDoctors)): ?>
                        <select name="doctor_id" class="form-select flat-input" id="doctorSelect">
                            <option value="">-- Select a Doctor --</option>
                            <?php foreach ($unlinkedDoctors as $d): ?>
                            <option value="<?= $d['id'] ?>">
                                Dr. <?= htmlspecialchars($d['first_name'].' '.$d['last_name']) ?> &mdash; <?= htmlspecialchars($d['specialization']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <div style="background:#fff7ed;border:1.5px solid #fed7aa;border-radius:0;padding:10px 14px;font-size:.83rem;color:#c2410c;display:flex;align-items:center;gap:8px">
                            <svg data-lucide="alert-triangle" width="15" height="15" style="flex-shrink:0"></svg>
                            No unlinked doctor profiles available. <a href="/clinic_1/public/doctors.php" style="color:#c2410c;font-weight:700;margin-left:4px">Add a doctor profile first &rarr;</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 mt-4 pt-2 border-top" style="border-color:var(--border)!important">
                    <button type="submit" name="<?= $edit_user ? 'update' : 'add' ?>" class="btn btn-primary">
                        <svg data-lucide="<?= $edit_user ? 'save' : 'user-plus' ?>"></svg>
                        <?= $edit_user ? 'Update Account' : 'Create Account' ?>
                    </button>
                    <?php if ($edit_user): ?>
                        <a href="/clinic_1/public/users.php" class="btn btn-outline-secondary">
                            <svg data-lucide="x"></svg> Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
<script>
function toggleDoctorField() {
    const roleEl = document.getElementById('roleSelect');
    if (!roleEl) return;
    const role  = roleEl.value;
    const field = document.getElementById('doctorField');
    if (!field) return;
    field.style.display = role === 'doctor' ? '' : 'none';
}
toggleDoctorField();
</script>
