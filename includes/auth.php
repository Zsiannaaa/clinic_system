<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Access denied.');
}

// ============================================================
// includes/auth.php — Authentication, RBAC & Session Security
//
// This is the MOST IMPORTANT file in the system.
// Included at the TOP of every protected page.
// Handles: login check, role check, logout, CSRF tokens.
// ============================================================

// session_cache_limiter('nocache') forces PHP to send no-cache headers
// automatically with every session response BEFORE session_start().
// This is a second layer on top of the manual headers in requireLogin().
session_cache_limiter('nocache');
session_cache_expire(0);

// session_start() MUST be called before any $_SESSION usage.
// It resumes the existing session or creates a new one.
session_start();

// $pdo (database connection) is loaded here so every page that
// includes auth.php automatically has access to the database.
require_once __DIR__ . '/../config/database.php';

/**
 * isLoggedIn() — returns true if the user has an active session.
 * At login we store user_id in $_SESSION. If it's set = logged in.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * getUserRole() — returns 'admin', 'receptionist', or 'doctor'.
 * The ?? '' means: return empty string if 'role' key doesn't exist.
 */
function getUserRole() {
    return $_SESSION['role'] ?? '';
}

/**
 * requireLogin() — gate that blocks non-logged-in users.
 * Also sets no-cache headers (Bonus 3) so the browser won't
 * restore protected pages after logout via the back button.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // Not logged in — send them to login, stop execution
        header("Location: /clinic_1/modules/auth/login.php");
        exit();
    }
    // Bonus 3: Tell the browser NEVER to cache this page.
    // Prevents the back-button from showing a protected page after logout.
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: Sat, 01 Jan 2000 00:00:00 GMT"); // Past date = no cache
}

/**
 * requireRole() — RBAC enforcement. The core of the permission system.
 * $allowed can be a string or array: requireRole(['admin','receptionist'])
 * First verifies login, then checks if the user's role is in the allowed list.
 * If not — redirects to dashboard with access_denied flag (Bonus 2).
 */
function requireRole($allowed) {
    requireLogin(); // Step 1: must be logged in
    if (!in_array(getUserRole(), (array)$allowed)) {
        // (array)$allowed converts a string to array so in_array() works either way
        // This redirect covers Bonus 2 — unauthorized access is safely redirected
        header("Location: /clinic_1/public/dashboard.php?error=access_denied");
        exit();
    }
}

/**
 * logoutUser() — Bonus 3: Secure logout sequence.
 * session_unset() = empties all $_SESSION variables.
 * session_destroy() = deletes the session data file from the server.
 * Both together fully invalidate the session.
 */
function logoutUser() {
    session_unset();   // Clears $_SESSION array (e.g. user_id, role, full_name)
    session_destroy(); // Removes session storage on server side
    header("Location: /clinic_1/modules/auth/login.php");
    exit();
}

// ── CSRF Protection ── Bonus 2 ────────────────────────────────
// CSRF = Cross-Site Request Forgery.
// Without this, an attacker could host a fake website with a form
// that posts to our delete.php — and if the admin is logged in,
// the delete would succeed. The token prevents this because the
// attacker's site can't know the secret token in our session.

/**
 * csrfToken() — generates a one-time-use secret token per session.
 * random_bytes(32) = 32 cryptographically random bytes.
 * bin2hex() converts those bytes to a 64-character hex string.
 * Stored in session so we can compare it against form submissions.
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * verifyCsrf() — validates the CSRF token submitted with a form.
 * hash_equals() is timing-safe — it prevents attackers from
 * guessing the token by measuring how long the comparison takes.
 * If tokens don't match = bad request, redirect with error.
 */
function verifyCsrf(string $redirectBack = 'index.php'): void {
    $submitted = $_POST['csrf_token'] ?? '';
    if (!$submitted || !hash_equals($_SESSION['csrf_token'] ?? '', $submitted)) {
        $_SESSION['error'] = 'Invalid or expired request. Please try again.';
        header("Location: $redirectBack");
        exit();
    }
}
?>
