<?php
require_once '../includes/auth.php';
require_once '../modules/queue_module.php';

requireRole(['admin', 'receptionist', 'doctor']);

$role = getUserRole();
$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);

if (isset($_POST['queue_action'])) {
    verifyCsrf('/clinic_1/public/queue.php');
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    $action = trim($_POST['queue_action']);

    $error = null;
    if (in_array($action, ['Waiting', 'In Service', 'Done', 'Skipped'], true)) {
        $error = updateQueueStatus($pdo, $appointmentId, $action, $userId, $role, $doctorId);
    } elseif ($action === 'toggle_priority') {
        $priority = trim($_POST['priority'] ?? 'Normal') === 'Priority' ? 'Normal' : 'Priority';
        $error = setQueuePriority($pdo, $appointmentId, $priority, $userId);
    } else {
        $error = 'Unknown action.';
    }

    $_SESSION[$error ? 'error' : 'success'] = $error ?? 'Queue updated.';
    header('Location: /clinic_1/public/queue.php');
    exit();
}

$filter = trim($_GET['filter'] ?? 'All');
$rows = getTodayQueue($pdo, $role, $doctorId, $filter);
$counts = getQueueCounts($pdo, $role, $doctorId);

require_once '../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Queue Board</h1>
        <div class="page-breadcrumb">Dashboard &rsaquo; Live Queue</div>
    </div>
    <div class="page-header-right">
        <a href="/clinic_1/public/queue_display.php" target="_blank" class="btn btn-outline-primary">
            <svg data-lucide="monitor"></svg> Open Screen Mode
        </a>
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

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="stat-card red flat-panel admin-kpi-cube">
            <div class="stat-icon red"><svg data-lucide="users"></svg></div>
            <div class="stat-body"><div class="stat-value"><?= (int)$counts['total'] ?></div><div class="stat-label">Today's Queue</div></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card blue flat-panel admin-kpi-cube">
            <div class="stat-icon blue"><svg data-lucide="clock"></svg></div>
            <div class="stat-body"><div class="stat-value"><?= (int)$counts['waiting'] ?></div><div class="stat-label">Waiting</div></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card green flat-panel admin-kpi-cube">
            <div class="stat-icon green"><svg data-lucide="calendar-check"></svg></div>
            <div class="stat-body"><div class="stat-value"><?= (int)$counts['in_service'] ?></div><div class="stat-label">In Service</div></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card gray flat-panel admin-kpi-cube">
            <div class="stat-icon gray"><svg data-lucide="check-circle"></svg></div>
            <div class="stat-body"><div class="stat-value"><?= (int)$counts['done'] ?></div><div class="stat-label">Done</div></div>
        </div>
    </div>
</div>

<div class="table-card flat-panel">
    <div class="card-header-section">
        <h5><svg data-lucide="calendar-clock" width="17" height="17"></svg>&nbsp;Today&apos;s Queue</h5>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <a href="/clinic_1/public/queue_export.php?filter=<?= urlencode($filter) ?>" class="btn btn-sm btn-outline-secondary">
                <svg data-lucide="save"></svg> Export
            </a>
            <?php foreach (['All', 'Waiting', 'In Service', 'Done', 'Skipped'] as $tab): ?>
            <a href="/clinic_1/public/queue.php?filter=<?= urlencode($tab) ?>"
               class="btn btn-sm <?= $filter === $tab ? 'btn-primary' : 'btn-outline-secondary' ?>">
               <?= htmlspecialchars($tab) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="clinic-table">
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Time</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Appt Status</th>
                    <th>Queue</th>
                    <th>Priority</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row):
                $qClass = match($row['queue_status']) {
                    'Done' => 'completed',
                    'Skipped' => 'cancelled',
                    'In Service' => 'scheduled',
                    default => 'scheduled'
                };
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars(formatQueueToken((int)($row['token_no'] ?? 0))) ?></strong></td>
                    <td><strong><?= date('h:i A', strtotime($row['appointment_datetime'])) ?></strong></td>
                    <td><?= htmlspecialchars($row['patient_name']) ?></td>
                    <td>Dr. <?= htmlspecialchars($row['doctor_name']) ?></td>
                    <td><?= htmlspecialchars($row['appointment_status']) ?></td>
                    <td><span class="status-badge <?= $qClass ?>"><span class="status-dot"></span><?= htmlspecialchars($row['queue_status']) ?></span></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="appointment_id" value="<?= (int)$row['appointment_id'] ?>">
                            <input type="hidden" name="queue_action" value="toggle_priority">
                            <input type="hidden" name="priority" value="<?= htmlspecialchars($row['priority']) ?>">
                            <button type="submit" class="btn btn-sm <?= $row['priority'] === 'Priority' ? 'btn-outline-danger' : 'btn-outline-secondary' ?>">
                                <?= htmlspecialchars($row['priority']) ?>
                            </button>
                        </form>
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="appointment_id" value="<?= (int)$row['appointment_id'] ?>">
                            <button name="queue_action" value="Waiting" class="btn btn-sm btn-outline-secondary">Check-in</button>
                            <button name="queue_action" value="In Service" class="btn btn-sm btn-outline-primary">Call</button>
                            <button name="queue_action" value="Done" class="btn btn-sm btn-outline-success">Done</button>
                            <button name="queue_action" value="Skipped" class="btn btn-sm btn-outline-warning">Skip</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8"><div class="empty-state"><svg data-lucide="calendar-x2"></svg><p>No queue entries for today.</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
