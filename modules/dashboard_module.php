<?php
// ============================================================
// modules/dashboard_module.php — Dashboard Data Model
//
// Queries dashboard statistics for each role.
// All values are computed live from the database — nothing hardcoded.
// Called by: public/dashboard.php
// ============================================================

/**
 * Admin dashboard: totals + upcoming appointments table.
 */
function getAdminDashboardData($pdo) {
    return [
        'totalDoctors'       => $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn(),
        'totalPatients'      => $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn(),
        'totalAppointments'  => $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn(),
        'todayAppointments'  => $pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime) = CURDATE()")->fetchColumn(),
        'recentAppointments' => $pdo->query("
            SELECT a.*, CONCAT(d.first_name,' ',d.last_name) AS doctor_name,
                   CONCAT(p.first_name,' ',p.last_name) AS patient_name
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            JOIN patients p ON a.patient_id = p.id
            WHERE a.status = 'Scheduled'
              AND a.appointment_datetime >= CURDATE()
            ORDER BY a.appointment_datetime ASC LIMIT 6
        ")->fetchAll(),
    ];
}

/**
 * Receptionist dashboard: today's schedule + totals.
 */
function getReceptionistDashboardData($pdo) {
    $todayAppts = $pdo->query("
        SELECT a.*, CONCAT(d.first_name,' ',d.last_name) AS doctor_name,
               CONCAT(p.first_name,' ',p.last_name) AS patient_name
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN patients p ON a.patient_id = p.id
        WHERE DATE(a.appointment_datetime) = CURDATE()
        ORDER BY a.appointment_datetime ASC
    ")->fetchAll();

    return [
        'todayAppointments'    => $pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_datetime)=CURDATE()")->fetchColumn(),
        'totalPatients'        => $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn(),
        'upcomingScheduled'    => $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='Scheduled' AND appointment_datetime>=NOW()")->fetchColumn(),
        'todayAppointmentList' => $todayAppts,
    ];
}

/**
 * Doctor dashboard: their own appointments only, filtered by doctor_id.
 */
function getDoctorDashboardData($pdo, $doctor_id) {
    $s1 = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND DATE(appointment_datetime)=CURDATE()");
    $s1->execute([$doctor_id]);

    $s2 = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=?");
    $s2->execute([$doctor_id]);

    $s3 = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND status='Scheduled' AND appointment_datetime>=NOW()");
    $s3->execute([$doctor_id]);

    $todayStmt = $pdo->prepare("
        SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.age, p.gender
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = ? AND DATE(a.appointment_datetime) = CURDATE()
        ORDER BY a.appointment_datetime ASC
    ");
    $todayStmt->execute([$doctor_id]);

    return [
        'myToday'    => $s1->fetchColumn(),
        'myTotal'    => $s2->fetchColumn(),
        'myUpcoming' => $s3->fetchColumn(),
        'todayAppts' => $todayStmt->fetchAll(),
    ];
}
