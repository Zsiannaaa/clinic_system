<?php
// ============================================================
// modules/queue_module.php - Queue Management Model
//
// Live queue tracking for today's appointments.
// ============================================================

function ensureQueueTable($pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS appointment_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NOT NULL UNIQUE,
            queue_status ENUM('Waiting','In Service','Done','Skipped') NOT NULL DEFAULT 'Waiting',
            priority ENUM('Normal','Priority') NOT NULL DEFAULT 'Normal',
            token_no INT NULL,
            queue_date DATE NULL,
            checked_in_at DATETIME NULL,
            called_at DATETIME NULL,
            completed_at DATETIME NULL,
            updated_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_queue_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
            CONSTRAINT fk_queue_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Backward-compatible migrations for earlier table versions.
    $cols = $pdo->query("SHOW COLUMNS FROM appointment_queue")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('token_no', $cols, true)) {
        $pdo->exec("ALTER TABLE appointment_queue ADD COLUMN token_no INT NULL AFTER priority");
    }
    if (!in_array('queue_date', $cols, true)) {
        $pdo->exec("ALTER TABLE appointment_queue ADD COLUMN queue_date DATE NULL AFTER token_no");
    }
}

function getNextQueueToken($pdo, string $queueDate): int {
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(token_no), 0) + 1 FROM appointment_queue WHERE queue_date = ?");
    $stmt->execute([$queueDate]);
    return (int)$stmt->fetchColumn();
}

function syncQueueRowsForToday($pdo): void {
    ensureQueueTable($pdo);

    $pdo->exec("
        INSERT IGNORE INTO appointment_queue (appointment_id)
        SELECT a.id
        FROM appointments a
        WHERE DATE(a.appointment_datetime) = CURDATE()
    ");

    $pdo->exec("
        UPDATE appointment_queue q
        JOIN appointments a ON a.id = q.appointment_id
        SET q.queue_status = CASE
                WHEN a.status = 'Completed' THEN 'Done'
                WHEN a.status = 'Cancelled' THEN 'Skipped'
                ELSE q.queue_status
            END,
            q.completed_at = CASE
                WHEN a.status IN ('Completed', 'Cancelled') AND q.completed_at IS NULL THEN NOW()
                ELSE q.completed_at
            END,
            q.queue_date = COALESCE(q.queue_date, DATE(a.appointment_datetime))
        WHERE DATE(a.appointment_datetime) = CURDATE()
    ");
}

function getTodayQueue($pdo, string $role, int $doctorId = 0, string $filter = 'All'): array {
    syncQueueRowsForToday($pdo);

    $allowedFilters = ['All', 'Waiting', 'In Service', 'Done', 'Skipped'];
    if (!in_array($filter, $allowedFilters, true)) {
        $filter = 'All';
    }

    $sql = "
        SELECT
            a.id AS appointment_id,
            a.doctor_id,
            a.patient_id,
            a.appointment_datetime,
            a.status AS appointment_status,
            a.appointment_type,
            CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            COALESCE(q.queue_status, 'Waiting') AS queue_status,
            COALESCE(q.priority, 'Normal') AS priority,
            q.token_no,
            q.queue_date,
            q.checked_in_at,
            q.called_at,
            q.completed_at
        FROM appointments a
        JOIN doctors d ON d.id = a.doctor_id
        JOIN patients p ON p.id = a.patient_id
        LEFT JOIN appointment_queue q ON q.appointment_id = a.id
        WHERE DATE(a.appointment_datetime) = CURDATE()
    ";

    $params = [];

    if ($role === 'doctor') {
        $sql .= " AND a.doctor_id = ? ";
        $params[] = $doctorId;
    }

    if ($filter !== 'All') {
        $sql .= " AND COALESCE(q.queue_status, 'Waiting') = ? ";
        $params[] = $filter;
    }

    $sql .= "
        ORDER BY
            FIELD(COALESCE(q.queue_status, 'Waiting'), 'In Service', 'Waiting', 'Done', 'Skipped'),
            FIELD(COALESCE(q.priority, 'Normal'), 'Priority', 'Normal'),
            COALESCE(q.token_no, 999999) ASC,
            a.appointment_datetime ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getQueueCounts($pdo, string $role, int $doctorId = 0): array {
    $rows = getTodayQueue($pdo, $role, $doctorId, 'All');
    $counts = ['total' => 0, 'waiting' => 0, 'in_service' => 0, 'done' => 0];

    foreach ($rows as $row) {
        $counts['total']++;
        if ($row['queue_status'] === 'Waiting') $counts['waiting']++;
        if ($row['queue_status'] === 'In Service') $counts['in_service']++;
        if ($row['queue_status'] === 'Done') $counts['done']++;
    }

    return $counts;
}

function updateQueueStatus($pdo, int $appointmentId, string $newStatus, int $userId, string $role, int $doctorId = 0): ?string {
    ensureQueueTable($pdo);
    $allowed = ['Waiting', 'In Service', 'Done', 'Skipped'];
    if (!in_array($newStatus, $allowed, true)) {
        return 'Invalid queue status.';
    }

    $stmt = $pdo->prepare("SELECT id, doctor_id, status, appointment_datetime FROM appointments WHERE id = ?");
    $stmt->execute([$appointmentId]);
    $appt = $stmt->fetch();
    if (!$appt) {
        return 'Appointment not found.';
    }

    if ($role === 'doctor' && (int)$appt['doctor_id'] !== $doctorId) {
        return 'Access denied. You can only manage your own queue.';
    }

    if (($appt['status'] ?? '') === 'Cancelled' && $newStatus !== 'Skipped') {
        return 'Cancelled appointments can only be marked as Skipped.';
    }

    if ($newStatus === 'In Service') {
        $pdo->prepare("
            UPDATE appointment_queue q
            JOIN appointments a ON a.id = q.appointment_id
            SET q.queue_status = 'Waiting'
            WHERE a.doctor_id = ?
              AND DATE(a.appointment_datetime) = CURDATE()
              AND q.queue_status = 'In Service'
              AND q.appointment_id != ?
        ")->execute([(int)$appt['doctor_id'], $appointmentId]);
    }

    $pdo->prepare("INSERT IGNORE INTO appointment_queue (appointment_id) VALUES (?)")->execute([$appointmentId]);

    $queueDate = date('Y-m-d', strtotime($appt['appointment_datetime']));
    $rowStmt = $pdo->prepare("SELECT token_no, queue_date FROM appointment_queue WHERE appointment_id = ? LIMIT 1");
    $rowStmt->execute([$appointmentId]);
    $queueRow = $rowStmt->fetch() ?: [];

    $tokenNo = $queueRow['token_no'] ?? null;
    if ($newStatus === 'Waiting' && !$tokenNo) {
        $tokenNo = getNextQueueToken($pdo, $queueDate);
    }

    $checkedIn = null;
    $calledAt = null;
    $completedAt = null;
    if ($newStatus === 'Waiting') {
        $checkedIn = date('Y-m-d H:i:s');
    } elseif ($newStatus === 'In Service') {
        $calledAt = date('Y-m-d H:i:s');
    } elseif (in_array($newStatus, ['Done', 'Skipped'], true)) {
        $completedAt = date('Y-m-d H:i:s');
    }

    $pdo->prepare("
        UPDATE appointment_queue
        SET queue_status = ?,
            token_no = COALESCE(?, token_no),
            queue_date = COALESCE(?, queue_date),
            checked_in_at = COALESCE(?, checked_in_at),
            called_at = COALESCE(?, called_at),
            completed_at = COALESCE(?, completed_at),
            updated_by = ?
        WHERE appointment_id = ?
    ")->execute([$newStatus, $tokenNo, $queueDate, $checkedIn, $calledAt, $completedAt, $userId, $appointmentId]);

    return null;
}

function setQueuePriority($pdo, int $appointmentId, string $priority, int $userId): ?string {
    ensureQueueTable($pdo);
    if (!in_array($priority, ['Normal', 'Priority'], true)) {
        return 'Invalid priority.';
    }

    $pdo->prepare("INSERT IGNORE INTO appointment_queue (appointment_id) VALUES (?)")->execute([$appointmentId]);
    $pdo->prepare("
        UPDATE appointment_queue
        SET priority = ?, updated_by = ?
        WHERE appointment_id = ?
    ")->execute([$priority, $userId, $appointmentId]);
    return null;
}

function getQueueDisplayData($pdo): array {
    syncQueueRowsForToday($pdo);
    $rows = getTodayQueue($pdo, 'admin', 0, 'All');

    $lanes = [];
    foreach ($rows as $row) {
        $doctorId = (int)$row['doctor_id'];
        if (!isset($lanes[$doctorId])) {
            $lanes[$doctorId] = [
                'doctor_id' => $doctorId,
                'doctor_name' => $row['doctor_name'],
                'now_serving' => null,
                'next_up' => null,
                'waiting' => [],
            ];
        }

        if ($row['queue_status'] === 'In Service' && $lanes[$doctorId]['now_serving'] === null) {
            $lanes[$doctorId]['now_serving'] = $row;
            continue;
        }

        if ($row['queue_status'] === 'Waiting') {
            $lanes[$doctorId]['waiting'][] = $row;
        }
    }

    foreach ($lanes as $doctorId => $lane) {
        if (!empty($lane['waiting'])) {
            usort($lane['waiting'], function ($a, $b) {
                $aToken = (int)($a['token_no'] ?? 999999);
                $bToken = (int)($b['token_no'] ?? 999999);
                if ($aToken === $bToken) {
                    return strcmp((string)$a['appointment_datetime'], (string)$b['appointment_datetime']);
                }
                return $aToken <=> $bToken;
            });
            $lanes[$doctorId]['next_up'] = $lane['waiting'][0];
        }
    }

    return array_values($lanes);
}

function formatQueueToken(?int $tokenNo): string {
    if (!$tokenNo || $tokenNo < 1) {
        return '--';
    }
    return 'Q' . str_pad((string)$tokenNo, 3, '0', STR_PAD_LEFT);
}
