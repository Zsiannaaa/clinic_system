<?php
require_once '../includes/auth.php';
require_once '../modules/ehr_module.php';

requireRole(['admin', 'receptionist', 'doctor']);

$role = getUserRole();
$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
$attachmentId = (int)($_GET['id'] ?? 0);

$attachment = $attachmentId ? getEhrAttachmentById($pdo, $attachmentId) : null;
if (!$attachment) {
    http_response_code(404);
    exit('Attachment not found.');
}

if ($role === 'doctor') {
    $check = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND doctor_id = ?");
    $check->execute([(int)$attachment['patient_id'], $doctorId]);
    if ((int)$check->fetchColumn() === 0) {
        http_response_code(403);
        exit('Access denied.');
    }
}

$base = realpath(__DIR__ . '/../assets/uploads/ehr');
if (!$base) {
    http_response_code(500);
    exit('Storage path not configured.');
}

$fileName = basename((string)$attachment['stored_name']);
$path = $base . DIRECTORY_SEPARATOR . $fileName;
if (!is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

$mime = $attachment['mime_type'] ?: 'application/octet-stream';
$downloadName = $attachment['original_name'] ?: 'attachment';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $downloadName) . '"');
readfile($path);
exit();
