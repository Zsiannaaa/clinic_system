<?php
require_once '../includes/auth.php';
require_once '../modules/billing_module.php';

requireRole(['admin', 'receptionist']);

$invoiceId = (int)($_GET['id'] ?? 0);
$invoice = $invoiceId ? getInvoiceById($pdo, $invoiceId) : null;
if (!$invoice) {
    http_response_code(404);
    exit('Invoice not found.');
}

$payments = getInvoicePayments($pdo, $invoiceId);
$paid = (float)$invoice['paid_amount'];
$total = (float)$invoice['total_amount'];
$balance = max(0, $total - $paid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= (int)$invoice['id'] ?></title>
    <style>
        body { font-family: Arial, sans-serif; color:#1f2937; margin:0; padding:24px; background:#fff; }
        .sheet { max-width: 860px; margin: 0 auto; border:1px solid #ddd; padding:24px; }
        .head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; }
        .brand { font-size: 1.4rem; font-weight: 700; color:#b42318; }
        .muted { color:#6b7280; }
        table { width:100%; border-collapse: collapse; margin-top:14px; }
        th, td { border:1px solid #e5e7eb; padding:10px; text-align:left; }
        th { background:#f9fafb; }
        .totals { margin-top:20px; margin-left:auto; width:320px; }
        .totals div { display:flex; justify-content:space-between; padding:6px 0; }
        .totals .grand { font-size:1.1rem; font-weight:700; border-top:1px solid #ddd; margin-top:6px; padding-top:10px; }
        .toolbar { max-width:860px; margin: 0 auto 12px; display:flex; gap:8px; }
        .toolbar button { padding:8px 14px; border:1px solid #d1d5db; background:#fff; cursor:pointer; }
        @media print {
            .toolbar { display:none; }
            body { padding:0; }
            .sheet { border:none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print Receipt</button>
        <button onclick="window.close()">Close</button>
    </div>
    <div class="sheet">
        <div class="head">
            <div>
                <div class="brand">Cryptalis Clinic</div>
                <div class="muted">Clinic Appointment Management System</div>
            </div>
            <div>
                <div><strong>Invoice #<?= (int)$invoice['id'] ?></strong></div>
                <div class="muted">Issued: <?= date('M d, Y h:i A', strtotime($invoice['issued_at'])) ?></div>
            </div>
        </div>

        <div>
            <strong>Patient:</strong> <?= htmlspecialchars($invoice['patient_name']) ?><br>
            <strong>Status:</strong> <?= htmlspecialchars($invoice['status']) ?><br>
            <?php if (!empty($invoice['notes'])): ?>
            <strong>Notes:</strong> <?= htmlspecialchars($invoice['notes']) ?><br>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Consultation / Services</td>
                    <td><?= number_format((float)$invoice['subtotal'], 2) ?></td>
                </tr>
                <?php if ((float)$invoice['discount'] > 0): ?>
                <tr>
                    <td>Discount</td>
                    <td>-<?= number_format((float)$invoice['discount'], 2) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals">
            <div><span>Invoice Total</span><span><?= number_format($total, 2) ?></span></div>
            <div><span>Total Paid</span><span><?= number_format($paid, 2) ?></span></div>
            <div class="grand"><span>Balance</span><span><?= number_format($balance, 2) ?></span></div>
        </div>

        <h4 style="margin-top:26px;">Payment History</h4>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Received By</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= date('M d, Y h:i A', strtotime($p['paid_at'])) ?></td>
                    <td><?= number_format((float)$p['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($p['received_by_name']) ?></td>
                    <td><?= htmlspecialchars($p['note'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($payments)): ?>
                <tr><td colspan="4" class="muted">No payment records yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
