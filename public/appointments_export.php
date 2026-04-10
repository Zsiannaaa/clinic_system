<?php
require_once '../includes/auth.php';
require_once '../modules/appointments_module.php';

requireRole(['admin', 'receptionist', 'doctor']);

$role = getUserRole();
$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? 'All');
if (!in_array($statusFilter, ['All', 'Scheduled', 'Completed', 'Cancelled'], true)) {
    $statusFilter = 'All';
}
$fromDate = trim($_GET['from'] ?? '');
$toDate = trim($_GET['to'] ?? '');
$hasDateRange = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate);

if ($hasDateRange) {
    $sql = "
        SELECT a.*, d.first_name AS doc_first, d.last_name AS doc_last,
               p.first_name AS pat_first, p.last_name AS pat_last
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN patients p ON a.patient_id = p.id
        WHERE DATE(a.appointment_datetime) BETWEEN ? AND ?
    ";
    $params = [$fromDate, $toDate];
    if ($role === 'doctor') {
        $sql .= " AND a.doctor_id = ? ";
        $params[] = $doctorId;
    }
    if ($statusFilter !== 'All') {
        $sql .= " AND a.status = ? ";
        $params[] = $statusFilter;
    }
    $sql .= " ORDER BY a.appointment_datetime DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} else {
    $total = countAppointments($pdo, $role, $doctorId, $statusFilter);
    $rows = $total > 0 ? getAppointments($pdo, $role, $doctorId, $total, 0, $statusFilter) : [];
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename=appointments_' . strtolower($statusFilter) . '.xls');
header('Pragma: no-cache');
header('Expires: 0');
echo "\xEF\xBB\xBF";

function escA($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        .title { font-size: 18px; font-weight: bold; color: #c0392b; }
        .sub { color: #6b7280; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; font-size: 12px; }
        th { background: #fef2f2; color: #7f1d1d; font-weight: bold; }
    </style>
</head>
<body>
    <div class="title">Cryptalis Clinic - Appointment Records Export</div>
    <div class="sub">
        Generated: <?= date('F j, Y h:i A') ?> |
        Role: <?= escA(ucfirst($role)) ?> |
        Filter: <?= escA($statusFilter) ?>
        <?php if ($hasDateRange): ?> |
        Range: <?= escA($fromDate) ?> to <?= escA($toDate) ?>
        <?php endif; ?>
    </div>
    <table>
        <tr>
            <th>Appointment ID</th>
            <th>Patient</th>
            <th>Doctor</th>
            <th>Date & Time</th>
            <th>Type</th>
            <th>Status</th>
            <th>Notes</th>
        </tr>
        <?php if (empty($rows)): ?>
        <tr><td colspan="7">No appointment records found.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= escA($r['id'] ?? '') ?></td>
                <td><?= escA(($r['pat_first'] ?? '') . ' ' . ($r['pat_last'] ?? '')) ?></td>
                <td><?= escA('Dr. ' . (($r['doc_first'] ?? '') . ' ' . ($r['doc_last'] ?? ''))) ?></td>
                <td><?= escA($r['appointment_datetime'] ?? '') ?></td>
                <td><?= escA($r['appointment_type'] ?? '') ?></td>
                <td><?= escA($r['status'] ?? '') ?></td>
                <td><?= escA($r['notes'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</body>
</html>
