<?php
require_once '../includes/auth.php';
require_once '../modules/queue_module.php';

requireRole(['admin', 'receptionist', 'doctor']);

$role = getUserRole();
$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
$filter = trim($_GET['filter'] ?? 'All');
if (!in_array($filter, ['All', 'Waiting', 'In Service', 'Done', 'Skipped'], true)) {
    $filter = 'All';
}

$rows = getTodayQueue($pdo, $role, $doctorId, $filter);

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename=queue_' . strtolower(str_replace(' ', '_', $filter)) . '_' . date('Ymd') . '.xls');
header('Pragma: no-cache');
header('Expires: 0');
echo "\xEF\xBB\xBF";

function escQ($v): string {
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
    <div class="title">Cryptalis Clinic - Queue Export</div>
    <div class="sub">Generated: <?= date('F j, Y h:i A') ?> | Filter: <?= escQ($filter) ?></div>
    <table>
        <tr>
            <th>Token</th>
            <th>Time</th>
            <th>Patient</th>
            <th>Doctor</th>
            <th>Appointment Status</th>
            <th>Queue Status</th>
            <th>Priority</th>
        </tr>
        <?php if (empty($rows)): ?>
        <tr><td colspan="7">No queue entries found.</td></tr>
        <?php else: foreach ($rows as $r): ?>
        <tr>
            <td><?= escQ(formatQueueToken((int)($r['token_no'] ?? 0))) ?></td>
            <td><?= escQ($r['appointment_datetime'] ?? '') ?></td>
            <td><?= escQ($r['patient_name'] ?? '') ?></td>
            <td><?= escQ('Dr. ' . ($r['doctor_name'] ?? '')) ?></td>
            <td><?= escQ($r['appointment_status'] ?? '') ?></td>
            <td><?= escQ($r['queue_status'] ?? '') ?></td>
            <td><?= escQ($r['priority'] ?? '') ?></td>
        </tr>
        <?php endforeach; endif; ?>
    </table>
</body>
</html>
