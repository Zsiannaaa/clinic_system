<?php
// ============================================================
// modules/ehr_module.php - EHR Notes Model
// ============================================================

function ensureEhrTable($pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS patient_ehr_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            doctor_id INT NULL,
            appointment_id INT NULL,
            created_by_user_id INT NOT NULL,
            note_type ENUM('Consultation','Diagnosis','Prescription','Follow-up','General') NOT NULL DEFAULT 'General',
            note_text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_ehr_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            CONSTRAINT fk_ehr_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
            CONSTRAINT fk_ehr_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
            CONSTRAINT fk_ehr_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS patient_ehr_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            note_id INT NOT NULL,
            original_name VARCHAR(190) NOT NULL,
            stored_name VARCHAR(190) NOT NULL UNIQUE,
            mime_type VARCHAR(120) NULL,
            file_size INT NOT NULL DEFAULT 0,
            uploaded_by_user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ehr_attachment_note FOREIGN KEY (note_id) REFERENCES patient_ehr_notes(id) ON DELETE CASCADE,
            CONSTRAINT fk_ehr_attachment_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function getEhrPatientsForRole($pdo, string $role, int $doctorId = 0): array {
    ensureEhrTable($pdo);

    if ($role === 'doctor') {
        $stmt = $pdo->prepare("
            SELECT DISTINCT p.*
            FROM patients p
            JOIN appointments a ON a.patient_id = p.id
            WHERE a.doctor_id = ?
            ORDER BY p.last_name ASC, p.first_name ASC
        ");
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll();
    }

    return $pdo->query("SELECT * FROM patients ORDER BY last_name ASC, first_name ASC")->fetchAll();
}

function getEhrNotes($pdo, int $patientId, string $role, int $doctorId = 0): array {
    ensureEhrTable($pdo);

    $sql = "
        SELECT n.*,
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, '')) AS doctor_name,
               u.full_name AS author_name
        FROM patient_ehr_notes n
        JOIN patients p ON p.id = n.patient_id
        LEFT JOIN doctors d ON d.id = n.doctor_id
        JOIN users u ON u.id = n.created_by_user_id
        WHERE n.patient_id = ?
    ";

    $params = [$patientId];
    if ($role === 'doctor') {
        $sql .= " AND (n.doctor_id = ? OR EXISTS (
                    SELECT 1 FROM appointments a
                    WHERE a.patient_id = n.patient_id
                      AND a.doctor_id = ?
                 ))";
        $params[] = $doctorId;
        $params[] = $doctorId;
    }

    $sql .= " ORDER BY n.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getEhrNoteById($pdo, int $noteId): ?array {
    ensureEhrTable($pdo);
    $stmt = $pdo->prepare("SELECT * FROM patient_ehr_notes WHERE id = ? LIMIT 1");
    $stmt->execute([$noteId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getEhrAttachmentsForNotes($pdo, array $noteIds): array {
    ensureEhrTable($pdo);
    $ids = array_values(array_filter(array_map('intval', $noteIds), fn($v) => $v > 0));
    if (empty($ids)) return [];

    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT id, note_id, original_name, stored_name, mime_type, file_size, created_at
        FROM patient_ehr_attachments
        WHERE note_id IN ($marks)
        ORDER BY created_at DESC
    ");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    $grouped = [];
    foreach ($rows as $r) {
        $nid = (int)$r['note_id'];
        if (!isset($grouped[$nid])) $grouped[$nid] = [];
        $grouped[$nid][] = $r;
    }
    return $grouped;
}

function createEhrNote(
    $pdo,
    int $patientId,
    ?int $doctorId,
    ?int $appointmentId,
    int $createdByUserId,
    string $role,
    string $noteType,
    string $noteText
): ?string {
    ensureEhrTable($pdo);

    if (!in_array($role, ['admin', 'doctor'], true)) {
        return 'Only admin and doctors can add EHR notes.';
    }

    $allowedTypes = ['Consultation', 'Diagnosis', 'Prescription', 'Follow-up', 'General'];
    if (!in_array($noteType, $allowedTypes, true)) {
        return 'Invalid note type.';
    }

    $noteText = trim($noteText);
    if ($noteText === '') {
        return 'Note text is required.';
    }

    if ($role === 'doctor' && !$doctorId) {
        return 'Doctor profile is missing. Please contact admin.';
    }

    if ($role === 'doctor') {
        $check = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND doctor_id = ?");
        $check->execute([$patientId, $doctorId]);
        if ((int)$check->fetchColumn() === 0) {
            return 'You can only create EHR notes for your assigned patients.';
        }
    }

    if ($appointmentId) {
        $appt = $pdo->prepare("SELECT id, patient_id, doctor_id FROM appointments WHERE id = ?");
        $appt->execute([$appointmentId]);
        $row = $appt->fetch();
        if (!$row) return 'Selected appointment does not exist.';
        if ((int)$row['patient_id'] !== $patientId) return 'Appointment and patient do not match.';
        if ($role === 'doctor' && (int)$row['doctor_id'] !== $doctorId) {
            return 'You can only attach notes to your own appointments.';
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO patient_ehr_notes
            (patient_id, doctor_id, appointment_id, created_by_user_id, note_type, note_text)
        VALUES (?,?,?,?,?,?)
    ");
    $stmt->execute([$patientId, $doctorId ?: null, $appointmentId ?: null, $createdByUserId, $noteType, $noteText]);
    return null;
}

function createEhrAttachment(
    $pdo,
    int $noteId,
    array $file,
    int $uploadedByUserId,
    string $role,
    int $doctorId = 0
): ?string {
    ensureEhrTable($pdo);

    if (!in_array($role, ['admin', 'doctor'], true)) {
        return 'Only admin and doctors can upload EHR attachments.';
    }

    $note = getEhrNoteById($pdo, $noteId);
    if (!$note) return 'Note not found.';

    if ($role === 'doctor') {
        if (!$doctorId) return 'Doctor profile is missing.';
        $check = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND doctor_id = ?");
        $check->execute([(int)$note['patient_id'], $doctorId]);
        if ((int)$check->fetchColumn() === 0) {
            return 'Access denied for this patient note.';
        }
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return 'Upload failed. Please try again.';
    }

    $maxBytes = 5 * 1024 * 1024;
    if ((int)$file['size'] > $maxBytes) {
        return 'Attachment is too large. Max size is 5MB.';
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']) ?: '';
    finfo_close($finfo);
    if (!isset($allowed[$mime])) {
        return 'Only JPG, PNG, PDF, and TXT files are allowed.';
    }

    $uploadDir = __DIR__ . '/../assets/uploads/ehr';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        return 'Unable to create upload directory.';
    }

    $ext = $allowed[$mime];
    $storedName = 'ehr_' . $noteId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath = $uploadDir . '/' . $storedName;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return 'Failed to save uploaded file.';
    }

    $stmt = $pdo->prepare("
        INSERT INTO patient_ehr_attachments
            (note_id, original_name, stored_name, mime_type, file_size, uploaded_by_user_id)
        VALUES (?,?,?,?,?,?)
    ");
    $stmt->execute([
        $noteId,
        mb_substr((string)($file['name'] ?? 'attachment'), 0, 180),
        $storedName,
        $mime,
        (int)$file['size'],
        $uploadedByUserId,
    ]);

    return null;
}

function getEhrAttachmentById($pdo, int $attachmentId): ?array {
    ensureEhrTable($pdo);
    $stmt = $pdo->prepare("
        SELECT a.*,
               n.patient_id,
               n.doctor_id AS note_doctor_id
        FROM patient_ehr_attachments a
        JOIN patient_ehr_notes n ON n.id = a.note_id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$attachmentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}
