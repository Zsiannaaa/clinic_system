<?php
// ============================================================
// modules/appointments_module.php — Appointment Model
//
// Contains ALL database logic for appointments.
// No HTML, no session logic — pure data functions.
// Called by: public/appointments.php, public/appointment_complete.php,
//            public/dashboard.php
//
// Business Rules enforced here (not in the view):
//   1. Doctor cannot be double-booked at the exact same datetime.
//   2. Patient cannot have two appointments at the exact same datetime.
//   3. Doctor can only update/complete their own appointments.
// ============================================================

// Change this single value if your instructor requires a different minimum gap.
const APPOINTMENT_GAP_MINUTES = 15;

function appointmentGapMinutes(): int {
    return max(1, (int)APPOINTMENT_GAP_MINUTES);
}

/**
 * Count total appointments filtered by role and optional status.
 */
function autoCancelOverdueAppointments($pdo, int $hoursOverdue = 24): int {
    $hoursOverdue = max(1, (int)$hoursOverdue);
    $stmt = $pdo->prepare("
        UPDATE appointments
        SET status = 'Cancelled'
        WHERE status = 'Scheduled'
          AND appointment_datetime < (NOW() - INTERVAL ? HOUR)
    ");
    $stmt->execute([$hoursOverdue]);
    return $stmt->rowCount();
}

/**
 * Count total appointments filtered by role and optional status.
 */
function countAppointments($pdo, $role, $doctor_id, $statusFilter = 'All') {
    if ($role === 'doctor') {
        if ($statusFilter !== 'All') {
            $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND status=?");
            $s->execute([$doctor_id, $statusFilter]);
        } else {
            $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=?");
            $s->execute([$doctor_id]);
        }
    } else {
        if ($statusFilter !== 'All') {
            $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE status=?");
            $s->execute([$statusFilter]);
        } else {
            $s = $pdo->query("SELECT COUNT(*) FROM appointments");
        }
    }
    return (int)$s->fetchColumn();
}

/**
 * Fetch paginated appointments with doctor and patient names via JOIN.
 * Doctor role only sees their own appointments.
 */
function getAppointments($pdo, $role, $doctor_id, $perPage, $offset, $statusFilter = 'All') {
    $base = "SELECT a.*, d.first_name AS doc_first, d.last_name AS doc_last,
                     p.first_name AS pat_first, p.last_name AS pat_last
             FROM appointments a
             JOIN doctors d ON a.doctor_id = d.id
             JOIN patients p ON a.patient_id = p.id";

    $perPage = max(1, (int)$perPage);
    $offset  = max(0, (int)$offset);

    if ($role === 'doctor') {
        if ($statusFilter !== 'All') {
            $stmt = $pdo->prepare("$base WHERE a.doctor_id = :doctor_id AND a.status = :status ORDER BY a.appointment_datetime DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':doctor_id', (int)$doctor_id, PDO::PARAM_INT);
            $stmt->bindValue(':status', $statusFilter, PDO::PARAM_STR);
        } else {
            $stmt = $pdo->prepare("$base WHERE a.doctor_id = :doctor_id ORDER BY a.appointment_datetime DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':doctor_id', (int)$doctor_id, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        if ($statusFilter !== 'All') {
            $stmt = $pdo->prepare("$base WHERE a.status = :status ORDER BY a.appointment_datetime DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':status', $statusFilter, PDO::PARAM_STR);
        } else {
            $stmt = $pdo->prepare("$base ORDER BY a.appointment_datetime DESC LIMIT :limit OFFSET :offset");
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

/**
 * Fetch a single appointment with doctor and patient names by ID.
 */
function getAppointmentById($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT a.*, d.first_name AS doc_first, d.last_name AS doc_last,
               p.first_name AS pat_first, p.last_name AS pat_last
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN patients p ON a.patient_id = p.id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Create a new appointment. Enforces Business Rules 1, 2, 3.
 * Returns error string or null on success.
 */
function createAppointment($pdo, $doctor_id, $patient_id, $datetime, $appointment_type, $notes) {
    $minGapMinutes = appointmentGapMinutes();

    // Business Rule 1: Doctor double-booking check (exact same datetime)
    $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_datetime = ? AND status = 'Scheduled'");
    $s->execute([$doctor_id, $datetime]);
    if ($s->fetchColumn() > 0) return 'This doctor already has an appointment at the selected date and time.';

    // Business Rule 1b: Doctor minimum-gap check (e.g., 15 minutes)
    $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'Scheduled' AND ABS(TIMESTAMPDIFF(MINUTE, appointment_datetime, ?)) < ?");
    $s->execute([$doctor_id, $datetime, $minGapMinutes]);
    if ($s->fetchColumn() > 0) return "This doctor already has an appointment within {$minGapMinutes} minutes of the selected time.";

    // Business Rule 2: Patient double-booking check (exact same datetime)
    $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND appointment_datetime = ? AND status = 'Scheduled'");
    $s->execute([$patient_id, $datetime]);
    if ($s->fetchColumn() > 0) return 'This patient already has an appointment at the selected date and time.';

    // Business Rule 2b: Patient minimum-gap check (e.g., 15 minutes)
    $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'Scheduled' AND ABS(TIMESTAMPDIFF(MINUTE, appointment_datetime, ?)) < ?");
    $s->execute([$patient_id, $datetime, $minGapMinutes]);
    if ($s->fetchColumn() > 0) return "This patient already has an appointment within {$minGapMinutes} minutes of the selected time.";

    // Business Rule 3: Appointment must be in the future
    if (strtotime($datetime) <= time()) return 'Appointment date and time must be set in the future.';

    $stmt = $pdo->prepare("INSERT INTO appointments (doctor_id, patient_id, appointment_datetime, appointment_type, notes) VALUES (?,?,?,?,?)");
    $stmt->execute([$doctor_id, $patient_id, $datetime, $appointment_type, $notes]);
    return null;
}

/**
 * Update an existing appointment. Excludes itself from double-booking check.
 * Returns error string or null on success.
 */
function updateAppointment($pdo, $id, $doctor_id, $patient_id, $datetime, $status, $appointment_type, $notes) {
    $minGapMinutes = appointmentGapMinutes();

    // Business Rule 1: Doctor conflict at exact same datetime (exclude current appointment)
    $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_datetime = ? AND status = 'Scheduled' AND id != ?");
    $s->execute([$doctor_id, $datetime, $id]);
    if ($s->fetchColumn() > 0) return 'This doctor already has an appointment at the selected date and time.';

    // Business Rule 1b: Doctor minimum-gap check (exclude current appointment)
    $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'Scheduled' AND id != ? AND ABS(TIMESTAMPDIFF(MINUTE, appointment_datetime, ?)) < ?");
    $s->execute([$doctor_id, $id, $datetime, $minGapMinutes]);
    if ($s->fetchColumn() > 0) return "This doctor already has an appointment within {$minGapMinutes} minutes of the selected time.";

    // Business Rule 2: Patient conflict at exact same datetime (exclude current appointment)
    $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND appointment_datetime = ? AND status = 'Scheduled' AND id != ?");
    $s->execute([$patient_id, $datetime, $id]);
    if ($s->fetchColumn() > 0) return 'This patient already has an appointment at the selected date and time.';

    // Business Rule 2b: Patient minimum-gap check (exclude current appointment)
    $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'Scheduled' AND id != ? AND ABS(TIMESTAMPDIFF(MINUTE, appointment_datetime, ?)) < ?");
    $s->execute([$patient_id, $id, $datetime, $minGapMinutes]);
    if ($s->fetchColumn() > 0) return "This patient already has an appointment within {$minGapMinutes} minutes of the selected time.";

    // Scheduled appointments must be in the future
    if ($status === 'Scheduled' && strtotime($datetime) <= time()) {
        return 'A scheduled appointment must be set to a future date and time.';
    }

    $stmt = $pdo->prepare("UPDATE appointments SET doctor_id=?, patient_id=?, appointment_datetime=?, status=?, appointment_type=?, notes=? WHERE id=?");
    $stmt->execute([$doctor_id, $patient_id, $datetime, $status, $appointment_type, $notes, $id]);
    return null;
}

/**
 * Delete an appointment (admin only action).
 * Safety rule: preserve clinical history by blocking delete unless:
 *   - status is Scheduled
 *   - appointment datetime is still in the future
 */
function deleteAppointment($pdo, $id) {
    $stmt = $pdo->prepare("SELECT status, appointment_datetime FROM appointments WHERE id = ?");
    $stmt->execute([$id]);
    $appt = $stmt->fetch();
    if (!$appt) {
        return 'Appointment not found.';
    }

    if (($appt['status'] ?? '') !== 'Scheduled') {
        return 'Only Scheduled appointments can be deleted. Keep completed/cancelled records for audit history.';
    }

    if (strtotime($appt['appointment_datetime']) <= time()) {
        return 'Past appointments cannot be deleted. Use Cancel for active schedules and keep history intact.';
    }

    $pdo->prepare("DELETE FROM appointments WHERE id = ?")->execute([$id]);
    return null;
}

/**
 * Update appointment status (Completed or Cancelled).
 * Business Rule 3: Doctor can only update their own appointment.
 * Returns error string or null.
 */
function updateAppointmentStatus($pdo, $id, $status, $role, $doctor_id = null) {
    if ($role === 'admin') {
        $allowed = ['Completed', 'Cancelled'];
    } elseif ($role === 'receptionist') {
        $allowed = ['Cancelled'];
    } else {
        $allowed = ['Completed'];
    }
    if (!in_array($status, $allowed)) return 'Invalid status for your role.';

    if (in_array($role, ['admin', 'receptionist'])) {
        $pdo->prepare("UPDATE appointments SET status=? WHERE id=?")->execute([$status, $id]);
    } else {
        // Business Rule 3: Doctor ownership check
        $check = $pdo->prepare("SELECT id FROM appointments WHERE id=? AND doctor_id=?");
        $check->execute([$id, $doctor_id]);
        if (!$check->fetch()) return 'Access denied. You can only update your own appointments.';
        $pdo->prepare("UPDATE appointments SET status=? WHERE id=? AND doctor_id=?")->execute([$status, $id, $doctor_id]);
    }
    return null;
}

/**
 * Mark appointment as Completed and save session notes.
 * Business Rule 3: Doctor can only complete their own appointments.
 * Returns error string or null.
 */
function completeAppointmentWithNotes($pdo, $id, $notes, $role, $doctor_id = null) {
    if ($role === 'doctor') {
        $check = $pdo->prepare("SELECT id FROM appointments WHERE id=? AND doctor_id=?");
        $check->execute([$id, $doctor_id]);
        if (!$check->fetch()) return 'Access denied. You can only complete your own appointments.';
        $pdo->prepare("UPDATE appointments SET status='Completed', notes=? WHERE id=? AND doctor_id=?")->execute([$notes, $id, $doctor_id]);
    } else {
        $pdo->prepare("UPDATE appointments SET status='Completed', notes=? WHERE id=?")->execute([$notes, $id]);
    }
    return null;
}
