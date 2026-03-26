<?php
// ============================================================
// modules/billing_module.php — Billing Model
//
// Basic invoice and payment tracking.
// ============================================================

function ensureBillingTables($pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS billing_invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            appointment_id INT NULL,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM('Unpaid','Partially Paid','Paid','Voided') NOT NULL DEFAULT 'Unpaid',
            notes VARCHAR(255) NULL,
            issued_by INT NOT NULL,
            issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_billing_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT,
            CONSTRAINT fk_billing_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
            CONSTRAINT fk_billing_issuer FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS billing_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            received_by INT NOT NULL,
            paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            note VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_billing_payment_invoice FOREIGN KEY (invoice_id) REFERENCES billing_invoices(id) ON DELETE CASCADE,
            CONSTRAINT fk_billing_payment_user FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function getBillingPatients($pdo): array {
    ensureBillingTables($pdo);
    return $pdo->query("SELECT id, first_name, last_name FROM patients ORDER BY last_name ASC, first_name ASC")->fetchAll();
}

function getBillingAppointmentsForPatient($pdo, int $patientId): array {
    ensureBillingTables($pdo);
    $stmt = $pdo->prepare("
        SELECT id, appointment_datetime, appointment_type, status
        FROM appointments
        WHERE patient_id = ?
        ORDER BY appointment_datetime DESC
        LIMIT 25
    ");
    $stmt->execute([$patientId]);
    return $stmt->fetchAll();
}

function createInvoice($pdo, int $patientId, ?int $appointmentId, float $subtotal, float $discount, string $notes, int $issuedBy): ?string {
    ensureBillingTables($pdo);
    if ($patientId <= 0) return 'Patient is required.';
    if ($subtotal <= 0) return 'Subtotal must be greater than 0.';
    if ($discount < 0) return 'Discount cannot be negative.';
    if ($discount > $subtotal) return 'Discount cannot exceed subtotal.';

    $total = max(0, $subtotal - $discount);

    if ($appointmentId) {
        $check = $pdo->prepare("SELECT id, patient_id FROM appointments WHERE id = ?");
        $check->execute([$appointmentId]);
        $row = $check->fetch();
        if (!$row) return 'Selected appointment does not exist.';
        if ((int)$row['patient_id'] !== $patientId) return 'Appointment does not belong to selected patient.';
    }

    $stmt = $pdo->prepare("
        INSERT INTO billing_invoices
            (patient_id, appointment_id, subtotal, discount, total_amount, notes, issued_by)
        VALUES (?,?,?,?,?,?,?)
    ");
    $stmt->execute([$patientId, $appointmentId ?: null, $subtotal, $discount, $total, trim($notes), $issuedBy]);
    return null;
}

function addInvoicePayment($pdo, int $invoiceId, float $amount, int $receivedBy, string $note): ?string {
    ensureBillingTables($pdo);
    if ($amount <= 0) return 'Payment amount must be greater than 0.';

    $invoice = getInvoiceById($pdo, $invoiceId);
    if (!$invoice) return 'Invoice not found.';
    if ($invoice['status'] === 'Voided') return 'Cannot pay a voided invoice.';

    $remaining = max(0, (float)$invoice['total_amount'] - (float)$invoice['paid_amount']);
    if ($amount > $remaining + 0.0001) return 'Payment exceeds remaining balance.';

    $stmt = $pdo->prepare("
        INSERT INTO billing_payments (invoice_id, amount, received_by, note)
        VALUES (?,?,?,?)
    ");
    $stmt->execute([$invoiceId, $amount, $receivedBy, trim($note)]);

    refreshInvoiceStatus($pdo, $invoiceId);
    return null;
}

function refreshInvoiceStatus($pdo, int $invoiceId): void {
    $invoice = getInvoiceById($pdo, $invoiceId);
    if (!$invoice) return;

    $paid = (float)$invoice['paid_amount'];
    $total = (float)$invoice['total_amount'];
    $status = 'Unpaid';
    if ($paid > 0 && $paid < $total) $status = 'Partially Paid';
    if ($paid >= $total && $total > 0) $status = 'Paid';

    $pdo->prepare("UPDATE billing_invoices SET status = ? WHERE id = ?")->execute([$status, $invoiceId]);
}

function getInvoices($pdo, ?int $patientId = null, string $status = 'All'): array {
    ensureBillingTables($pdo);
    $sql = "
        SELECT i.*,
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               COALESCE(pay.total_paid, 0) AS paid_amount
        FROM billing_invoices i
        JOIN patients p ON p.id = i.patient_id
        LEFT JOIN (
            SELECT invoice_id, SUM(amount) AS total_paid
            FROM billing_payments
            GROUP BY invoice_id
        ) pay ON pay.invoice_id = i.id
        WHERE 1=1
    ";
    $params = [];

    if ($patientId) {
        $sql .= " AND i.patient_id = ? ";
        $params[] = $patientId;
    }

    $allowed = ['All', 'Unpaid', 'Partially Paid', 'Paid', 'Voided'];
    if (in_array($status, $allowed, true) && $status !== 'All') {
        $sql .= " AND i.status = ? ";
        $params[] = $status;
    }

    $sql .= " ORDER BY i.issued_at DESC, i.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getInvoiceById($pdo, int $invoiceId): ?array {
    ensureBillingTables($pdo);
    $stmt = $pdo->prepare("
        SELECT i.*,
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               COALESCE(pay.total_paid, 0) AS paid_amount
        FROM billing_invoices i
        JOIN patients p ON p.id = i.patient_id
        LEFT JOIN (
            SELECT invoice_id, SUM(amount) AS total_paid
            FROM billing_payments
            GROUP BY invoice_id
        ) pay ON pay.invoice_id = i.id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmt->execute([$invoiceId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getInvoicePayments($pdo, int $invoiceId): array {
    ensureBillingTables($pdo);
    $stmt = $pdo->prepare("
        SELECT bp.*, u.full_name AS received_by_name
        FROM billing_payments bp
        JOIN users u ON u.id = bp.received_by
        WHERE bp.invoice_id = ?
        ORDER BY bp.paid_at DESC
    ");
    $stmt->execute([$invoiceId]);
    return $stmt->fetchAll();
}
