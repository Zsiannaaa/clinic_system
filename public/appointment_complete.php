<?php
// ============================================================
// public/appointment_complete.php — Complete Appointment + AI Notes
//
// MVC Role:
//   Model      → modules/appointments_module.php
//   Controller → access check + form processing at top
//   View       → AI-powered notes editor below
//
// Access:
//   Doctor — can only complete their OWN appointments
//   Admin  — can complete any appointment
// ============================================================
require_once '../includes/auth.php';
require_once '../modules/appointments_module.php';
require_once '../config/ai.php';

requireRole(['doctor', 'admin']);
$role = getUserRole();
$id   = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: /clinic_1/public/appointments.php'); exit(); }

$appt = getAppointmentById($pdo, $id);
if (!$appt) { header('Location: /clinic_1/public/appointments.php'); exit(); }

// Business Rule 3: Doctor can only complete their own appointments
if ($role === 'doctor' && ($appt['doctor_id'] ?? 0) != ($_SESSION['doctor_id'] ?? 0)) {
    $_SESSION['error'] = 'Access Denied: You can only complete your own appointments.';
    header('Location: /clinic_1/public/appointments.php'); exit();
}

// Only Scheduled appointments can be completed
if ($appt['status'] !== 'Scheduled') {
    $_SESSION['error'] = 'Only Scheduled appointments can be marked as completed.';
    header('Location: /clinic_1/public/appointments.php'); exit();
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf('/clinic_1/public/appointments.php');
    $notes = trim($_POST['notes'] ?? '');
    $err   = completeAppointmentWithNotes($pdo, $id, $notes, $role, $_SESSION['doctor_id'] ?? null);
    if ($err) { $error = $err; }
    else {
        $_SESSION['success'] = 'Appointment marked as Completed.';
        header('Location: /clinic_1/public/appointments.php'); exit();
    }
}

require_once '../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Complete Appointment</h1>
        <div class="page-breadcrumb">
            <a href="/clinic_1/public/appointments.php"><?= $role === 'doctor' ? 'My Appointments' : 'Appointments' ?></a>
            &rsaquo; Complete #<?= $id ?>
        </div>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger mb-4">
    <svg data-lucide="alert-circle" width="17" height="17"></svg> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Left: appointment summary -->
    <div class="col-md-4">
        <div class="table-card h-100">
            <div class="card-header-section">
                <h5><svg data-lucide="calendar-check" width="17" height="17"></svg>&nbsp;Appointment</h5>
            </div>
            <div class="p-4" style="font-size:.875rem">
                <?php $fields = [
                    ['label'=>'Patient', 'value'=>$appt['pat_first'].' '.$appt['pat_last']],
                    ['label'=>'Doctor',  'value'=>'Dr. '.$appt['doc_first'].' '.$appt['doc_last']],
                    ['label'=>'Date',    'value'=>date('F d, Y', strtotime($appt['appointment_datetime']))],
                    ['label'=>'Time',    'value'=>date('h:i A', strtotime($appt['appointment_datetime']))],
                    ['label'=>'Type',    'value'=>$appt['appointment_type'] ?? 'Check-up'],
                ]; foreach ($fields as $f): ?>
                <div style="margin-bottom:16px">
                    <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:3px"><?= $f['label'] ?></div>
                    <div style="font-weight:<?= $f['label']==='Patient'?'700':'600' ?>;font-size:<?= $f['label']==='Patient'?'1rem':'.875rem' ?>"><?= htmlspecialchars($f['value']) ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (!empty($appt['notes'])): ?>
                <div>
                    <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:3px">Previous Notes</div>
                    <div style="color:var(--text-secondary);font-style:italic"><?= htmlspecialchars($appt['notes']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

















    
    
    <!-- Right: AI notes editor -->
    <div class="col-md-8">
        <div class="table-card">
            <div class="card-header-section">
                <h5><svg data-lucide="file-text" width="17" height="17"></svg>&nbsp;Session Notes</h5>
                <span class="complete-ai-pill">
                    ✦ AI Powered
                </span>
            </div>
            <div class="p-4">
                <div class="complete-ai-help">
                    <svg data-lucide="sparkles" width="15" height="15" class="complete-ai-help-icon"></svg>
                    <span>Type your shorthand notes, then click <strong>Summarize with AI</strong> to rewrite them into a professional clinical summary.</span>
                </div>

                <form method="POST" id="completeForm">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <label class="form-label complete-notes-label">
                        Session Notes
                        <span class="complete-notes-sub">(shorthand or full notes)</span>
                    </label>
                    <textarea id="notesInput" name="notes" class="form-control complete-notes-input" rows="7" placeholder="e.g. pt complains of headaches x3 days. bp 130/85. prescribed ibuprofen 400mg tid x5days. advised hydration and rest. f/u 2 weeks."><?= htmlspecialchars($appt['notes'] ?? '') ?></textarea>

                    <div class="complete-ai-actions">
                        <button type="button" id="aiSummarizeBtn" class="complete-ai-summarize-btn">
                            <svg data-lucide="sparkles" width="14" height="14"></svg>
                            <span id="aiSummarizeBtnText">Summarize with AI</span>
                        </button>
                        <span id="aiStatus" class="complete-ai-status"></span>
                    </div>

                    <div id="aiResultBox" class="complete-ai-result-box">
                        <div class="complete-ai-result-title">
                            <svg data-lucide="sparkles" width="12" height="12"></svg> AI Summary Preview
                        </div>
                        <div id="aiResultText" class="complete-ai-result-text"></div>
                        <div class="complete-ai-result-actions">
                            <button type="button" id="aiAcceptBtn" class="complete-ai-accept-btn">
                                ✓ Use this summary
                            </button>
                            <button type="button" id="aiDiscardBtn" class="complete-ai-discard-btn">
                                ✗ Discard, keep my notes
                            </button>
                        </div>
                    </div>

                    <div class="complete-submit-row">
                        <button type="submit" class="btn btn-primary">
                            <svg data-lucide="check-circle"></svg> Mark as Completed
                        </button>
                        <a href="/clinic_1/public/appointments.php" class="btn btn-outline-secondary">
                            <svg data-lucide="x"></svg> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const notesInput   = document.getElementById('notesInput');
    const summarizeBtn = document.getElementById('aiSummarizeBtn');
    const btnText      = document.getElementById('aiSummarizeBtnText');
    const aiStatus     = document.getElementById('aiStatus');
    const aiResultBox  = document.getElementById('aiResultBox');
    const aiResultText = document.getElementById('aiResultText');
    const acceptBtn    = document.getElementById('aiAcceptBtn');
    const discardBtn   = document.getElementById('aiDiscardBtn');
    let lastSummary    = '';

    summarizeBtn.addEventListener('click', function () {
        const notes = notesInput.value.trim();
        if (!notes) { aiStatus.textContent = 'Please write some notes first.'; aiStatus.style.color = '#e53e3e'; return; }
        summarizeBtn.disabled = true; btnText.textContent = 'Summarizing...';
        aiStatus.textContent = 'AI is rewriting your notes...'; aiStatus.style.color = '#c0392b';
        aiResultBox.style.display = 'none';
        const form = new FormData(); form.append('mode', 'summarize'); form.append('notes', notes);
        fetch('/clinic_1/modules/ai/chat.php', { method: 'POST', body: form })
            .then(r => r.json())
            .then(data => {
                if (data.error) { aiStatus.textContent = 'Error: ' + data.error; aiStatus.style.color = '#e53e3e'; }
                else { lastSummary = data.reply; aiResultText.textContent = data.reply; aiResultBox.style.display = 'block'; aiStatus.textContent = 'Done! Review below.'; aiStatus.style.color = '#059669'; }
            })
            .catch(() => { aiStatus.textContent = 'Network error. Try again.'; aiStatus.style.color = '#e53e3e'; })
            .finally(() => { summarizeBtn.disabled = false; btnText.textContent = 'Summarize with AI'; });
    });
    acceptBtn.addEventListener('click',  () => { notesInput.value = lastSummary; aiResultBox.style.display = 'none'; aiStatus.textContent = 'AI summary applied.'; aiStatus.style.color = '#059669'; });
    discardBtn.addEventListener('click', () => { aiResultBox.style.display = 'none'; aiStatus.textContent = ''; });
})();
</script>

<?php require_once '../includes/footer.php'; ?>


