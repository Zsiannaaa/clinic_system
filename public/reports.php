<?php
require_once '../includes/auth.php';
requireRole(['admin', 'receptionist']);

$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$monthStart = date('Y-m-01');

require_once '../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Reports & Exports</h1>
        <div class="page-breadcrumb">Dashboard &rsaquo; Reports</div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="table-card flat-panel">
            <div class="card-header-section">
                <h5><svg data-lucide="users" width="17" height="17"></svg>&nbsp;Patients</h5>
            </div>
            <div class="p-3 d-grid gap-2">
                <a class="btn btn-outline-secondary" href="/clinic_1/public/patients_export.php">Export All Patients</a>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="table-card flat-panel">
            <div class="card-header-section">
                <h5><svg data-lucide="calendar-check" width="17" height="17"></svg>&nbsp;Appointments</h5>
            </div>
            <div class="p-3">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a class="btn btn-outline-secondary" href="/clinic_1/public/appointments_export.php?from=<?= $today ?>&to=<?= $today ?>">Daily</a>
                    <a class="btn btn-outline-secondary" href="/clinic_1/public/appointments_export.php?from=<?= $weekStart ?>&to=<?= $today ?>">Weekly</a>
                    <a class="btn btn-outline-secondary" href="/clinic_1/public/appointments_export.php?from=<?= $monthStart ?>&to=<?= $today ?>">Monthly</a>
                </div>
                <form method="GET" action="/clinic_1/public/appointments_export.php" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select flat-input">
                            <option value="All">All</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">From</label>
                        <input type="date" name="from" class="form-control flat-input" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To</label>
                        <input type="date" name="to" class="form-control flat-input" required>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100">Export Custom</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="table-card flat-panel">
            <div class="card-header-section">
                <h5><svg data-lucide="file-text" width="17" height="17"></svg>&nbsp;Billing</h5>
            </div>
            <div class="p-3">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a class="btn btn-outline-secondary" href="/clinic_1/public/billing_export.php?from=<?= $today ?>&to=<?= $today ?>">Daily</a>
                    <a class="btn btn-outline-secondary" href="/clinic_1/public/billing_export.php?from=<?= $weekStart ?>&to=<?= $today ?>">Weekly</a>
                    <a class="btn btn-outline-secondary" href="/clinic_1/public/billing_export.php?from=<?= $monthStart ?>&to=<?= $today ?>">Monthly</a>
                </div>
                <form method="GET" action="/clinic_1/public/billing_export.php" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select flat-input">
                            <option value="All">All</option>
                            <option value="Unpaid">Unpaid</option>
                            <option value="Partially Paid">Partially Paid</option>
                            <option value="Paid">Paid</option>
                            <option value="Voided">Voided</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">From</label>
                        <input type="date" name="from" class="form-control flat-input" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To</label>
                        <input type="date" name="to" class="form-control flat-input" required>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100">Export</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="table-card flat-panel">
            <div class="card-header-section">
                <h5><svg data-lucide="clock" width="17" height="17"></svg>&nbsp;Queue</h5>
            </div>
            <div class="p-3 d-grid gap-2">
                <a class="btn btn-outline-secondary" href="/clinic_1/public/queue_export.php?filter=All">Export Today Queue (All)</a>
                <a class="btn btn-outline-secondary" href="/clinic_1/public/queue_export.php?filter=Waiting">Export Waiting Only</a>
                <a class="btn btn-outline-secondary" href="/clinic_1/public/queue_export.php?filter=In%20Service">Export In Service</a>
                <a class="btn btn-outline-secondary" href="/clinic_1/public/queue_export.php?filter=Done">Export Done</a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
