<?php
// ============================================================
// modules/doctors_module.php — Doctor Model
//
// Contains ALL database logic for the doctors entity.
// No HTML, no session checks, no output — pure functions.
// Each function receives $pdo so it is fully self-contained.
// Called by: public/doctors.php (the Controller + View layer)
// ============================================================

/**
 * Fetch a paginated list of doctors ordered by last name.
 */
function getDoctors($pdo, $perPage = 10, $offset = 0) {
    $perPage = max(1, (int)$perPage);
    $offset  = max(0, (int)$offset);
    $stmt = $pdo->prepare("SELECT * FROM doctors ORDER BY last_name ASC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Return the total count of doctors (used for pagination).
 */
function countDoctors($pdo) {
    return (int)$pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
}

/**
 * Fetch a single doctor row by primary key.
 */
function getDoctorById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Fetch all doctors for dropdown menus (appointments form).
 */
function getDoctorsForDropdown($pdo) {
    return $pdo->query("SELECT * FROM doctors ORDER BY last_name ASC")->fetchAll();
}

/**
 * Compute availability snapshot for doctors shown on the current page.
 *
 * Status precedence:
 *   1) Currently in Schedule ([time]) within +/- $busyWindowMinutes
 *   2) Next Appointment at [time] (next scheduled appointment later today)
 *   3) Available Now
 */
function getDoctorAvailabilityStatuses($pdo, array $doctorIds, int $busyWindowMinutes = 30): array {
    $doctorIds = array_values(array_filter(array_map('intval', $doctorIds), fn($id) => $id > 0));
    if (empty($doctorIds)) {
        return [];
    }

    $busyWindowMinutes = max(1, $busyWindowMinutes);
    $now = new DateTimeImmutable('now');
    $busyStart = $now->modify("-{$busyWindowMinutes} minutes")->format('Y-m-d H:i:s');
    $busyEnd   = $now->modify("+{$busyWindowMinutes} minutes")->format('Y-m-d H:i:s');
    $nowStr    = $now->format('Y-m-d H:i:s');

    $placeholders = implode(',', array_fill(0, count($doctorIds), '?'));
    $sql = "
        SELECT doctor_id, appointment_datetime
        FROM appointments
        WHERE status = 'Scheduled'
          AND doctor_id IN ($placeholders)
          AND (
            appointment_datetime BETWEEN ? AND ?
            OR (appointment_datetime > ? AND DATE(appointment_datetime) = CURDATE())
          )
        ORDER BY appointment_datetime ASC
    ";

    $stmt = $pdo->prepare($sql);
    $params = array_merge($doctorIds, [$busyStart, $busyEnd, $nowStr]);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $statusMap = [];
    foreach ($doctorIds as $id) {
        $statusMap[$id] = [
            'type' => 'available',
            'label' => 'Available Now',
            'next' => null,
        ];
    }

    foreach ($rows as $row) {
        $doctorId = (int)$row['doctor_id'];
        $dt       = $row['appointment_datetime'];
        if (!isset($statusMap[$doctorId])) {
            continue;
        }

        if ($dt >= $busyStart && $dt <= $busyEnd) {
            $statusMap[$doctorId]['type']  = 'busy';
            $statusMap[$doctorId]['label'] = 'Currently in Schedule: ' . date('g:i A', strtotime($dt));
            $statusMap[$doctorId]['next']  = null;
            continue;
        }

        if ($statusMap[$doctorId]['type'] === 'available' && $dt > $nowStr) {
            $statusMap[$doctorId]['type']  = 'next';
            $statusMap[$doctorId]['label'] = 'Next Appointment: ' . date('g:i A', strtotime($dt));
            $statusMap[$doctorId]['next']  = $dt;
        }
    }

    return $statusMap;
}

/**
 * Insert a new doctor. Returns an error string on failure, null on success.
 */
function createDoctor($pdo, $first_name, $last_name, $specialization, $contact_number) {
    if (!$first_name || !$last_name || !$specialization) {
        return 'First name, last name, and specialization are required.';
    }
    $stmt = $pdo->prepare("INSERT INTO doctors (first_name, last_name, specialization, contact_number) VALUES (?,?,?,?)");
    $stmt->execute([$first_name, $last_name, $specialization, $contact_number]);
    return null;
}

/**
 * Update an existing doctor record. Returns error string or null.
 */
function updateDoctor($pdo, $id, $first_name, $last_name, $specialization, $contact_number) {
    if (!$first_name || !$last_name || !$specialization) {
        return 'First name, last name, and specialization are required.';
    }
    $stmt = $pdo->prepare("UPDATE doctors SET first_name=?, last_name=?, specialization=?, contact_number=? WHERE id=?");
    $stmt->execute([$first_name, $last_name, $specialization, $contact_number, $id]);
    return null;
}

/**
 * Delete a doctor.
 * Safety rule: preserve historical records by blocking delete if ANY appointment exists.
 * Returns error string or null.
 */
function deleteDoctor($pdo, $id) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        return 'Cannot delete this doctor. Appointment history exists. Keep the doctor record to preserve medical records.';
    }
    $pdo->prepare("DELETE FROM doctors WHERE id = ?")->execute([$id]);
    return null;
}
