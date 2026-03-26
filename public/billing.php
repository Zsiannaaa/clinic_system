<?php
require_once '../includes/auth.php';
require_once '../modules/billing_module.php';

requireRole(['admin', 'receptionist']);

$userId = (int)($_SESSION['user_id'] ?? 0);

if (isset($_POST['create_invoice'])) {
    verifyCsrf('/clinic_1/public/billing.php');
    $err = createInvoice(
        $pdo,
        (int)($_POST['patient_id'] ?? 0),
        ((int)($_POST['appointment_id'] ?? 0) ?: null),
        (float)($_POST['subtotal'] ?? 0),
        (float)($_POST['discount'] ?? 0),
        trim($_POST['notes'] ?? ''),
        $userId
    );
    $_SESSION[$err ? 'error' : 'success'] = $err ?? 'Invoice created.';
    header('Location: /clinic_1/public/billing.php');
    exit();
}

if (isset($_POST['add_payment'])) {
    verifyCsrf('/clinic_1/public/billing.php');
    $invoiceId = (int)($_POST['invoice_id'] ?? 0);
    $err = addInvoicePayment(
        $pdo,
        $invoiceId,
        (float)($_POST['payment_amount'] ?? 0),
        $userId,
        trim($_POST['payment_note'] ?? '')
    );
    $_SESSION[$err ? 'error' : 'success'] = $err ?? 'Payment recorded.';
    header('Location: /clinic_1/public/billing.php?invoice_id=' . $invoiceId);
    exit();
}

$selectedStatus = trim($_GET['status'] ?? 'All');
$selectedPatientId = ((int)($_GET['patient_id'] ?? 0) ?: null);
$selectedInvoiceId = (int)($_GET['invoice_id'] ?? 0);

$patients = getBillingPatients($pdo);
$invoices = getInvoices($pdo, $selectedPatientId, $selectedStatus);
$invoice = $selectedInvoiceId ? getInvoiceById($pdo, $selectedInvoiceId) : null;
$invoicePayments = $selectedInvoiceId ? getInvoicePayments($pdo, $selectedInvoiceId) : [];
$patientAppointments = $selectedPatientId ? getBillingAppointmentsForPatient($pdo, $selectedPatientId) : [];

require_once '../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Billing</h1>
        <div class="page-breadcrumb">Dashboard &rsaquo; Invoices & Payments</div>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success mb-4">
    <svg data-lucide="check-circle" width="17" height="17"></svg>
    <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
</div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger mb-4">
    <svg data-lucide="alert-circle" width="17" height="17"></svg>
    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<div class="row g-3 billing-layout">
    <div class="col-lg-8">
        <div class="table-card flat-panel">
            <div class="card-header-section">
                <h5><svg data-lucide="file-text" width="17" height="17"></svg>&nbsp;Invoice Ledger</h5>
                <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap">
                    <select name="status" class="form-select flat-input" style="min-width:160px">
                        <?php foreach (['All', 'Unpaid', 'Partially Paid', 'Paid', 'Voided'] as $s): ?>
                        <option value="<?= $s ?>" <?= $selectedStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="patient_id" class="form-select flat-input" style="min-width:220px">
                        <option value="">All Patients</option>
                        <?php foreach ($patients as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $selectedPatientId === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['last_name'] . ', ' . $p['first_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-secondary btn-sm">Filter</button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="clinic-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Patient</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv):
                            $paid = (float)$inv['paid_amount'];
                            $total = (float)$inv['total_amount'];
                            $balance = max(0, $total - $paid);
                            $badge = match($inv['status']) {
                                'Paid' => 'completed',
                                'Partially Paid' => 'scheduled',
                                'Voided' => 'cancelled',
                                default => 'scheduled'
                            };
                        ?>
                        <tr>
                            <td>#<?= (int)$inv['id'] ?></td>
                            <td><?= htmlspecialchars($inv['patient_name']) ?></td>
                            <td><?= number_format($total, 2) ?></td>
                            <td><?= number_format($paid, 2) ?></td>
                            <td><?= number_format($balance, 2) ?></td>
                            <td><span class="status-badge <?= $badge ?>"><span class="status-dot"></span><?= htmlspecialchars($inv['status']) ?></span></td>
                            <td style="text-align:right">
                                <a href="/clinic_1/public/billing.php?invoice_id=<?= (int)$inv['id'] ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                <a href="/clinic_1/public/billing_receipt.php?id=<?= (int)$inv['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank">Print</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($invoices)): ?>
                        <tr><td colspan="7"><div class="empty-state"><svg data-lucide="file-text"></svg><p>No invoices found.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="form-card flat-panel mb-3">
            <h5 style="margin-bottom:18px;font-weight:700">
                <svg data-lucide="plus-circle" width="17" height="17"></svg>&nbsp;Create Invoice
            </h5>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="mb-3">
                    <label class="form-label">Patient</label>
                    <select name="patient_id" class="form-select flat-input" required onchange="window.location='?patient_id='+this.value">
                        <option value="">Select patient</option>
                        <?php foreach ($patients as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $selectedPatientId === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['last_name'] . ', ' . $p['first_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Appointment (optional)</label>
                    <select name="appointment_id" class="form-select flat-input">
                        <option value="">No linked appointment</option>
                        <?php foreach ($patientAppointments as $a): ?>
                        <option value="<?= (int)$a['id'] ?>">
                            #<?= (int)$a['id'] ?> - <?= date('M d, Y h:i A', strtotime($a['appointment_datetime'])) ?> (<?= htmlspecialchars($a['appointment_type']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Subtotal</label>
                        <input type="number" name="subtotal" min="0.01" step="0.01" class="form-control flat-input" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Discount</label>
                        <input type="number" name="discount" min="0" step="0.01" class="form-control flat-input" value="0">
                    </div>
                </div>
                <div class="mt-3 mb-3">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control flat-input" placeholder="Optional billing note">
                </div>
                <button class="btn btn-primary" name="create_invoice" value="1">
                    <svg data-lucide="save"></svg> Create Invoice
                </button>
            </form>
        </div>

        <?php if ($invoice): ?>
        <?php $balance = max(0, (float)$invoice['total_amount'] - (float)$invoice['paid_amount']); ?>
        <div class="form-card flat-panel">
            <h5 style="margin-bottom:10px;font-weight:700">
                <svg data-lucide="calendar-check" width="17" height="17"></svg>&nbsp;Invoice #<?= (int)$invoice['id'] ?>
            </h5>
            <div style="font-size:.9rem;color:var(--text-muted);margin-bottom:8px"><?= htmlspecialchars($invoice['patient_name']) ?></div>
            <div style="margin-bottom:10px">
                <strong>Total:</strong> <?= number_format((float)$invoice['total_amount'], 2) ?><br>
                <strong>Paid:</strong> <?= number_format((float)$invoice['paid_amount'], 2) ?><br>
                <strong>Balance:</strong> <?= number_format($balance, 2) ?>
            </div>
            <form method="POST" class="mb-3">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>">
                <div class="mb-2">
                    <label class="form-label">Payment Amount</label>
                    <input type="number" name="payment_amount" min="0.01" step="0.01" class="form-control flat-input" <?= $balance <= 0 ? 'disabled' : '' ?> required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Note</label>
                    <input type="text" name="payment_note" class="form-control flat-input" placeholder="Cash / Card / Reference">
                </div>
                <button class="btn btn-outline-success" name="add_payment" value="1" <?= $balance <= 0 ? 'disabled' : '' ?>>
                    <svg data-lucide="plus-circle"></svg> Add Payment
                </button>
            </form>
            <div class="table-responsive">
                <table class="clinic-table">
                    <thead><tr><th>When</th><th>Amount</th></tr></thead>
                    <tbody>
                        <?php foreach ($invoicePayments as $p): ?>
                        <tr>
                            <td><?= date('M d, Y h:i A', strtotime($p['paid_at'])) ?></td>
                            <td><?= number_format((float)$p['amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($invoicePayments)): ?>
                        <tr><td colspan="2"><span style="color:var(--text-muted)">No payments yet.</span></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
