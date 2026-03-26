<?php
// ============================================================
// modules/patients_module.php — Patient Model
//
// Contains ALL database logic for the patients entity.
// No HTML, no session checks — pure data functions.
// Called by: public/patients.php, public/patient_view.php
// ============================================================

/**
 * Fetch a paginated list of patients ordered by last name.
 */
function getPatients($pdo, $perPage = 10, $offset = 0) {
    $perPage = max(1, (int)$perPage);
    $offset  = max(0, (int)$offset);
    $stmt = $pdo->prepare("SELECT * FROM patients ORDER BY last_name ASC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Return total patient count (for pagination).
 */
function countPatients($pdo) {
    return (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
}

/**
 * Fetch a single patient row by primary key.
 */
function getPatientById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Fetch all patients for dropdown menus (appointments form).
 */
function getPatientsForDropdown($pdo) {
    return $pdo->query("SELECT * FROM patients ORDER BY last_name ASC")->fetchAll();
}

/**
 * Check if a patient has at least one appointment with a specific doctor.
 * Used by the Doctor role to verify they are allowed to view a patient.
 */
function isPatientAssignedToDoctor($pdo, $patient_id, $doctor_id) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND patient_id = ?");
    $check->execute([$doctor_id, $patient_id]);
    return $check->fetchColumn() > 0;
}

/**
 * Insert a new patient. Returns error string or null on success.
 */
function createPatient($pdo, $first_name, $last_name, $gender, $dob, $contact_number, $address) {
    if (!$first_name || !$last_name || !$dob || !$gender) {
        return 'All required fields must be filled.';
    }
    if (strtotime($dob) > time()) {
        return 'Date of birth cannot be in the future.';
    }
    $age = (int)(new DateTime())->diff(new DateTime($dob))->y;
    if ($age < 1) {
        return 'Patient must be at least 1 year old.';
    }
    if ($contact_number && !preg_match('/^[0-9\+\-\s\(\)]{7,20}$/', $contact_number)) {
        return 'Contact number must contain only digits, spaces, +, -, or parentheses (7–20 characters).';
    }
    $stmt = $pdo->prepare("INSERT INTO patients (first_name, last_name, age, gender, date_of_birth, contact_number, address) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$first_name, $last_name, $age, $gender, $dob, $contact_number, $address]);
    return null;
}

/**
 * Update an existing patient record. Returns error string or null.
 */
function updatePatient($pdo, $id, $first_name, $last_name, $gender, $dob, $contact_number, $address) {
    if (!$first_name || !$last_name || !$dob || !$gender) {
        return 'All required fields must be filled.';
    }
    if (strtotime($dob) > time()) {
        return 'Date of birth cannot be in the future.';
    }
    $age = (int)(new DateTime())->diff(new DateTime($dob))->y;
    if ($age < 1) {
        return 'Patient must be at least 1 year old.';
    }
    if ($contact_number && !preg_match('/^[0-9\+\-\s\(\)]{7,20}$/', $contact_number)) {
        return 'Contact number must contain only digits, spaces, +, -, or parentheses (7–20 characters).';
    }
    $stmt = $pdo->prepare("UPDATE patients SET first_name=?, last_name=?, age=?, gender=?, date_of_birth=?, contact_number=?, address=? WHERE id=?");
    $stmt->execute([$first_name, $last_name, $age, $gender, $dob, $contact_number, $address, $id]);
    return null;
}

/**
 * Delete a patient.
 * Safety rule: preserve historical records by blocking delete if ANY appointment exists.
 * Returns error string or null.
 */
function deletePatient($pdo, $id) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        return 'Cannot delete this patient. Appointment history exists. Keep the patient record to preserve medical records.';
    }
    $pdo->prepare("DELETE FROM patients WHERE id = ?")->execute([$id]);
    return null;
}

/**
 * Fetch appointment history for a patient — filtered by role.
 * Doctor only sees their own appointments with this patient.
 */
function getPatientAppointmentHistory($pdo, $patient_id, $role, $doctor_id = null) {
    if ($role === 'doctor') {
        $stmt = $pdo->prepare("
            SELECT a.*, d.first_name AS doc_first, d.last_name AS doc_last
            FROM appointments a JOIN doctors d ON a.doctor_id = d.id
            WHERE a.patient_id = ? AND a.doctor_id = ?
            ORDER BY a.appointment_datetime DESC
        ");
        $stmt->execute([$patient_id, $doctor_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT a.*, d.first_name AS doc_first, d.last_name AS doc_last
            FROM appointments a JOIN doctors d ON a.doctor_id = d.id
            WHERE a.patient_id = ?
            ORDER BY a.appointment_datetime DESC
        ");
        $stmt->execute([$patient_id]);
    }
    return $stmt->fetchAll();
}
