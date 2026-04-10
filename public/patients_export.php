<?php
require_once '../includes/auth.php';
require_once '../modules/patients_module.php';

requireRole(['admin', 'receptionist']);

$patientId = (int)($_GET['patient_id'] ?? 0);
$mode = $patientId > 0 ? 'single' : 'list';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . ($mode === 'single' ? 'patient_record_' . $patientId . '.xls' : 'patients_registry.xls'));
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF"; // UTF-8 BOM

function esc($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if ($mode === 'single') {
    $patient = getPatientById($pdo, $patientId);
    if (!$patient) {
        echo '<table><tr><td>Patient not found.</td></tr></table>';
        exit();
    }

    $history = getPatientAppointmentHistory($pdo, $patientId, getUserRole(), (int)($_SESSION['doctor_id'] ?? 0));
    $fullName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));

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
            .meta td:first-child { font-weight: bold; width: 180px; background: #f9fafb; }
        </style>
    </head>
    <body>
        <div class="title">Cryptalis Clinic - Patient Record Export</div>
        <div class="sub">Generated: <?= date('F j, Y h:i A') ?></div>
        <table class="meta">
            <tr><td>Patient Name</td><td><?= esc($fullName) ?></td></tr>
            <tr><td>Age</td><td><?= esc($patient['age'] ?? '') ?></td></tr>
            <tr><td>Gender</td><td><?= esc($patient['gender'] ?? '') ?></td></tr>
            <tr><td>Date of Birth</td><td><?= esc($patient['date_of_birth'] ?? '') ?></td></tr>
            <tr><td>Contact Number</td><td><?= esc($patient['contact_number'] ?? '') ?></td></tr>
            <tr><td>Address</td><td><?= esc($patient['address'] ?? '') ?></td></tr>
        </table>

        <table>
            <tr>
                <th>Appointment ID</th>
                <th>Date & Time</th>
                <th>Doctor</th>
                <th>Type</th>
                <th>Status</th>
                <th>Notes</th>
            </tr>
            <?php if (empty($history)): ?>
            <tr><td colspan="6">No appointment history found.</td></tr>
            <?php else: ?>
                <?php foreach ($history as $row): ?>
                <tr>
                    <td><?= esc($row['id'] ?? '') ?></td>
                    <td><?= esc($row['appointment_datetime'] ?? '') ?></td>
                    <td><?= esc('Dr. ' . (($row['doc_first'] ?? '') . ' ' . ($row['doc_last'] ?? ''))) ?></td>
                    <td><?= esc($row['appointment_type'] ?? '') ?></td>
                    <td><?= esc($row['status'] ?? '') ?></td>
                    <td><?= esc($row['notes'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </body>
    </html>
    <?php
    exit();
}

$patients = getAllPatientsForExport($pdo);
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
    <div class="title">Cryptalis Clinic - Patient Registry Export</div>
    <div class="sub">Generated: <?= date('F j, Y h:i A') ?></div>
    <table>
        <tr>
            <th>ID</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Age</th>
            <th>Gender</th>
            <th>Date of Birth</th>
            <th>Contact Number</th>
            <th>Address</th>
            <th>Created At</th>
        </tr>
        <?php if (empty($patients)): ?>
        <tr><td colspan="9">No patient records found.</td></tr>
        <?php else: ?>
            <?php foreach ($patients as $p): ?>
            <tr>
                <td><?= esc($p['id'] ?? '') ?></td>
                <td><?= esc($p['first_name'] ?? '') ?></td>
                <td><?= esc($p['last_name'] ?? '') ?></td>
                <td><?= esc($p['age'] ?? '') ?></td>
                <td><?= esc($p['gender'] ?? '') ?></td>
                <td><?= esc($p['date_of_birth'] ?? '') ?></td>
                <td><?= esc($p['contact_number'] ?? '') ?></td>
                <td><?= esc($p['address'] ?? '') ?></td>
                <td><?= esc($p['created_at'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</body>
</html>
