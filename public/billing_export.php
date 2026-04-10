<?php
require_once '../includes/auth.php';
require_once '../modules/billing_module.php';

requireRole(['admin', 'receptionist']);

$status = trim($_GET['status'] ?? 'All');
$patientId = ((int)($_GET['patient_id'] ?? 0) ?: null);
$invoiceId = (int)($_GET['invoice_id'] ?? 0);
$fromDate = trim($_GET['from'] ?? '');
$toDate = trim($_GET['to'] ?? '');
$hasDateRange = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate);

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Pragma: no-cache');
header('Expires: 0');
echo "\xEF\xBB\xBF";

function escB($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if ($invoiceId > 0) {
    $invoice = getInvoiceById($pdo, $invoiceId);
    if (!$invoice) {
        header('Content-Disposition: attachment; filename=billing_invoice_not_found.xls');
        echo '<table><tr><td>Invoice not found.</td></tr></table>';
        exit();
    }
    $payments = getInvoicePayments($pdo, $invoiceId);
    $paid = (float)$invoice['paid_amount'];
    $total = (float)$invoice['total_amount'];
    $balance = max(0, $total - $paid);

    header('Content-Disposition: attachment; filename=billing_invoice_' . $invoiceId . '.xls');
    ?>
    <html><head><meta charset="UTF-8"><style>
    .title{font-size:18px;font-weight:bold;color:#c0392b}.sub{color:#6b7280;font-size:12px}
    table{border-collapse:collapse;width:100%;margin-top:10px}th,td{border:1px solid #d1d5db;padding:8px;font-size:12px}
    th{background:#fef2f2;color:#7f1d1d;font-weight:bold}.meta td:first-child{font-weight:bold;width:180px;background:#f9fafb}
    </style></head><body>
    <div class="title">Cryptalis Clinic - Invoice Export</div>
    <div class="sub">Generated: <?= date('F j, Y h:i A') ?></div>
    <table class="meta">
        <tr><td>Invoice ID</td><td>#<?= escB($invoice['id']) ?></td></tr>
        <tr><td>Patient</td><td><?= escB($invoice['patient_name']) ?></td></tr>
        <tr><td>Issued At</td><td><?= escB($invoice['issued_at']) ?></td></tr>
        <tr><td>Status</td><td><?= escB($invoice['status']) ?></td></tr>
        <tr><td>Total</td><td><?= number_format($total, 2) ?></td></tr>
        <tr><td>Paid</td><td><?= number_format($paid, 2) ?></td></tr>
        <tr><td>Balance</td><td><?= number_format($balance, 2) ?></td></tr>
    </table>
    <table>
        <tr><th>Date</th><th>Amount</th><th>Received By</th><th>Note</th></tr>
        <?php if (empty($payments)): ?>
        <tr><td colspan="4">No payments yet.</td></tr>
        <?php else: foreach ($payments as $p): ?>
        <tr>
            <td><?= escB($p['paid_at'] ?? '') ?></td>
            <td><?= number_format((float)($p['amount'] ?? 0), 2) ?></td>
            <td><?= escB($p['received_by_name'] ?? '') ?></td>
            <td><?= escB($p['note'] ?? '') ?></td>
        </tr>
        <?php endforeach; endif; ?>
    </table>
    </body></html>
    <?php
    exit();
}

$invoices = getInvoices($pdo, $patientId, $status);
if ($hasDateRange) {
    $fromDt = $fromDate . ' 00:00:00';
    $toDt = $toDate . ' 23:59:59';
    $invoices = array_values(array_filter($invoices, static function ($inv) use ($fromDt, $toDt) {
        $issued = (string)($inv['issued_at'] ?? '');
        return $issued >= $fromDt && $issued <= $toDt;
    }));
}
header('Content-Disposition: attachment; filename=billing_ledger.xls');
?>
<html><head><meta charset="UTF-8"><style>
.title{font-size:18px;font-weight:bold;color:#c0392b}.sub{color:#6b7280;font-size:12px}
table{border-collapse:collapse;width:100%;margin-top:10px}th,td{border:1px solid #d1d5db;padding:8px;font-size:12px}
th{background:#fef2f2;color:#7f1d1d;font-weight:bold}
</style></head><body>
<div class="title">Cryptalis Clinic - Billing Ledger Export</div>
<div class="sub">
Generated: <?= date('F j, Y h:i A') ?> | Status: <?= escB($status) ?>
<?php if ($hasDateRange): ?> | Range: <?= escB($fromDate) ?> to <?= escB($toDate) ?><?php endif; ?>
</div>
<table>
    <tr>
        <th>Invoice #</th><th>Patient</th><th>Subtotal</th><th>Discount</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Issued At</th>
    </tr>
    <?php if (empty($invoices)): ?>
    <tr><td colspan="9">No invoices found.</td></tr>
    <?php else: foreach ($invoices as $inv): $paid=(float)$inv['paid_amount']; $total=(float)$inv['total_amount']; $bal=max(0,$total-$paid); ?>
    <tr>
        <td>#<?= escB($inv['id']) ?></td>
        <td><?= escB($inv['patient_name']) ?></td>
        <td><?= number_format((float)$inv['subtotal'], 2) ?></td>
        <td><?= number_format((float)$inv['discount'], 2) ?></td>
        <td><?= number_format($total, 2) ?></td>
        <td><?= number_format($paid, 2) ?></td>
        <td><?= number_format($bal, 2) ?></td>
        <td><?= escB($inv['status']) ?></td>
        <td><?= escB($inv['issued_at']) ?></td>
    </tr>
    <?php endforeach; endif; ?>
</table>
</body></html>
