<?php
// ============================================================
// modules/ai/chat.php — 
//
// Two modes:
//   mode=chat      — Floating chatbox, role-aware medical assistant.
//   mode=summarize — Rewrites raw doctor notes into a professional
//                    clinical summary (used by complete.php).
//
// Always returns JSON: {reply:"..."} or {error:"..."}
// Only logged-in users can call this endpoint.
// ============================================================
require_once '../../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required.']); exit();
}

require_once '../../config/ai.php';

$role = getUserRole();
$mode = trim($_POST['mode'] ?? 'chat');

function aiInitThreads(): void {
    if (!isset($_SESSION['ai_threads']) || !is_array($_SESSION['ai_threads'])) {
        $_SESSION['ai_threads'] = [];
    }
}

function aiThreadPreview(array $messages): string {
    foreach ($messages as $msg) {
        if (($msg['role'] ?? '') === 'user' && !empty($msg['text'])) {
            return mb_substr(trim((string)$msg['text']), 0, 90);
        }
    }
    return 'New Chat';
}

function aiThreadTitle(array $messages): string {
    $preview = aiThreadPreview($messages);
    return mb_substr($preview, 0, 38);
}

function aiListThreads(): array {
    aiInitThreads();
    $threads = array_values($_SESSION['ai_threads']);
    usort($threads, function ($a, $b) {
        return strcmp(($b['updated_at'] ?? ''), ($a['updated_at'] ?? ''));
    });
    return array_map(function ($thread) {
        $messages = $thread['messages'] ?? [];
        return [
            'id'         => $thread['id'] ?? '',
            'title'      => $thread['title'] ?? 'New Chat',
            'preview'    => aiThreadPreview($messages),
            'updated_at' => $thread['updated_at'] ?? '',
            'count'      => count($messages),
        ];
    }, $threads);
}

function aiCreateThread(string $title = 'New Chat'): array {
    aiInitThreads();
    $id = 't_' . bin2hex(random_bytes(8));
    $now = date('Y-m-d H:i:s');
    $thread = [
        'id'         => $id,
        'title'      => trim($title) !== '' ? trim($title) : 'New Chat',
        'created_at' => $now,
        'updated_at' => $now,
        'messages'   => [],
    ];
    $_SESSION['ai_threads'][$id] = $thread;
    return $thread;
}

// —— Chat history endpoints for the standalone Medical AI page —————————
if ($mode === 'history_list') {
    echo json_encode(['threads' => aiListThreads()]); exit();
}

if ($mode === 'history_create') {
    $thread = aiCreateThread(trim($_POST['title'] ?? 'New Chat'));
    echo json_encode(['thread' => $thread, 'threads' => aiListThreads()]); exit();
}

if ($mode === 'history_get') {
    aiInitThreads();
    $threadId = trim($_POST['thread_id'] ?? '');
    if ($threadId === '' || !isset($_SESSION['ai_threads'][$threadId])) {
        echo json_encode(['error' => 'Chat thread not found.']); exit();
    }
    echo json_encode(['thread' => $_SESSION['ai_threads'][$threadId]]); exit();
}

if ($mode === 'history_delete') {
    aiInitThreads();
    $threadId = trim($_POST['thread_id'] ?? '');
    if ($threadId !== '' && isset($_SESSION['ai_threads'][$threadId])) {
        unset($_SESSION['ai_threads'][$threadId]);
    }
    echo json_encode(['ok' => true, 'threads' => aiListThreads()]); exit();
}

// ── Summarize mode ───────────────────────────────────────────
if ($mode === 'summarize') {
    $rawNotes = trim($_POST['notes'] ?? '');
    if (!$rawNotes) {
        echo json_encode(['error' => 'No notes provided.']); exit();
    }

    $system = "You are a professional medical documentation assistant for Cryptalis Clinic. "
            . "Rewrite the doctor's shorthand session notes into a clear, professional clinical summary. "
            . "Use structured medical language. Format with labeled sections: "
            . "Chief Complaint, Clinical Findings, Assessment, Treatment & Prescriptions, Follow-up Plan. "
            . "Keep it concise, accurate, and suitable for a medical record. "
            . "Do NOT add invented details — only reformat what is given.";

    $contents = [[
        'role'  => 'user',
        'parts' => [['text' => "Rewrite these session notes professionally:\n\n{$rawNotes}"]]
    ]];

    echo json_encode(['reply' => callGemini($contents, $system)]);
    exit();
}

// ── Chat mode ────────────────────────────────────────────────
$message = trim($_POST['message'] ?? '');
if (!$message) {
    echo json_encode(['error' => 'Message cannot be empty.']); exit();
}

$history = json_decode($_POST['history'] ?? '[]', true);
if (!is_array($history)) $history = [];
$persistHistory = trim((string)($_POST['persist_history'] ?? '')) === '1';
$threadId = trim($_POST['thread_id'] ?? '');

// Role-specific system prompts
$systemPrompts = [
    'doctor' => "You are a clinical medical assistant for Cryptalis Clinic. "
              . "Help doctors with: symptom analysis, differential diagnoses, drug interactions, "
              . "treatment protocols, dosage guidelines, and medical terminology. "
              . "Be precise, evidence-based, and professional. "
              . "Always recommend verifying with current clinical guidelines (e.g. UpToDate, WHO). "
              . "Do not provide specific diagnoses for individual named patients.",

    'receptionist' => "You are a friendly medical front-desk assistant for Cryptalis Clinic. "
                    . "Help staff with: patient intake questions, appointment guidance, "
                    . "common health conditions explained simply, and general clinic operations. "
                    . "Keep responses clear and jargon-free. "
                    . "For serious medical questions from patients, always advise them to consult the doctor.",

    'admin' => "You are a medical clinic operations assistant for Cryptalis Clinic. "
             . "Help with both clinical and administrative questions: medical terminology, "
             . "clinic management, patient record guidance, staff coordination, and health policy. "
             . "Be professional and concise.",
];

$system  = ($systemPrompts[$role] ?? $systemPrompts['admin']);
$system .= "\n\nIDENTITY RULE: If the user asks who trained you, who built you, who developed you, or who created this assistant, "
         . "answer clearly that you were configured and developed by Zsian Morales for Cryptalis Clinic.";
$system .= "\n\nDISCLAIMER RULE: End responses that involve clinical advice with a brief note "
         . "that this is AI-generated information for reference only, not a substitute for "
         . "professional medical judgment.";

// Build contents array (conversation history + new message)
$contents = [];

if ($persistHistory) {
    aiInitThreads();
    if ($threadId === '' || !isset($_SESSION['ai_threads'][$threadId])) {
        $thread = aiCreateThread('New Chat');
        $threadId = $thread['id'];
    }
    $threadMessages = $_SESSION['ai_threads'][$threadId]['messages'] ?? [];
    foreach ($threadMessages as $item) {
        if (isset($item['role'], $item['text'])) {
            $contents[] = [
                'role'  => $item['role'],
                'parts' => [['text' => $item['text']]]
            ];
        }
    }
} else {
    foreach ($history as $item) {
        if (isset($item['role'], $item['text'])) {
            $contents[] = [
                'role'  => $item['role'],
                'parts' => [['text' => $item['text']]]
            ];
        }
    }
}
$contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

$reply = callGemini($contents, $system);

if ($persistHistory && $threadId !== '') {
    $now = date('Y-m-d H:i:s');
    if (!isset($_SESSION['ai_threads'][$threadId])) {
        $thread = aiCreateThread('New Chat');
        $threadId = $thread['id'];
    }
    $_SESSION['ai_threads'][$threadId]['messages'][] = ['role' => 'user',  'text' => $message, 'at' => $now];
    $_SESSION['ai_threads'][$threadId]['messages'][] = ['role' => 'model', 'text' => $reply,   'at' => $now];

    // Keep chat compact to avoid oversized session payload.
    $maxMessages = 50;
    if (count($_SESSION['ai_threads'][$threadId]['messages']) > $maxMessages) {
        $_SESSION['ai_threads'][$threadId]['messages'] = array_slice($_SESSION['ai_threads'][$threadId]['messages'], -$maxMessages);
    }

    $_SESSION['ai_threads'][$threadId]['updated_at'] = $now;
    if (trim($_SESSION['ai_threads'][$threadId]['title'] ?? '') === 'New Chat') {
        $_SESSION['ai_threads'][$threadId]['title'] = aiThreadTitle($_SESSION['ai_threads'][$threadId]['messages']);
    }

    // Keep only the latest 20 threads.
    $threads = aiListThreads();
    $allowed = array_slice(array_column($threads, 'id'), 0, 20);
    $_SESSION['ai_threads'] = array_intersect_key($_SESSION['ai_threads'], array_flip($allowed));
}

echo json_encode([
    'reply' => $reply,
    'thread_id' => $threadId ?: null,
]);
