<?php
// ============================================================
// modules/auth/login.php — Login Page (Part 1 Requirement)
//
// This is the entry point for all users. It handles:
//   1. Redirecting already-logged-in users to dashboard.
//   2. Processing the login form (POST request).
//   3. Verifying credentials against the database.
//   4. Storing user info in session on success.
//   5. Showing the split-panel login UI on GET request.
// ============================================================
require_once '../../includes/auth.php';

// If user is already logged in (session exists), skip login page
// and send them straight to the dashboard.
if (isLoggedIn()) {
    header("Location: /clinic_1/public/dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // trim() removes accidental spaces. ?? '' gives empty string if field missing.
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Step 1: Look up the user by username only (NOT password in SQL).
    // We never put the password in the SQL query — we verify it in PHP.
    $stmt = $pdo->prepare("SELECT id, password, role, full_name FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(); // Returns associative array or false if not found

    // Step 2: password_verify() compares the plain-text input against
    // the bcrypt hash stored in the database. Even if two users have the
    // same password, the hashes will be different (bcrypt uses salt).
    if ($user && password_verify($password, $user['password'])) {

        // Step 3: Store essential user info in the session.
        // This is what every requireLogin() and getUserRole() check reads.
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['role']      = $user['role'];    // 'admin','receptionist','doctor'
        $_SESSION['full_name'] = $user['full_name'];

        // Step 4: If this user is a doctor, also load their doctor record ID.
        // The doctors table links back to users via user_id.
        // We need $_SESSION['doctor_id'] so the doctor dashboard and
        // appointment filter know which doctor this is.
        if ($user['role'] === 'doctor') {
            $stmt2 = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
            $stmt2->execute([$user['id']]);
            if ($doc = $stmt2->fetch()) {
                $_SESSION['doctor_id'] = $doc['id']; // Used in appointment queries
            }
        }

        // All session data set — redirect to dashboard
        header("Location: /clinic_1/public/dashboard.php");
        exit();
    } else {
        // Wrong username or wrong password — same generic message for security.
        // Never tell the attacker WHICH one was wrong.
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cryptalis Clinic — Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/clinic_1/assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="login-wrapper">

    <!-- ══ LEFT PANEL ══ -->
    <div class="panel-left">
        <div class="bg-photo"></div>
        <div class="bg-overlay"></div>
        <div class="panel-content">

            <!-- Top brand -->
            <div class="brand-top">
                <div class="logo-icon"><img src="/clinic_1/assets/images/logo.png" alt="Cryptalis Clinic"></div>
                <span class="brand-name">Cryptalis Clinic</span>
            </div>

            <!-- Bottom tagline -->
            <div class="tagline">
                <h2>Your Health,<br>Our Priority.</h2>
                <p>Empowering healthcare professionals with a secure, efficient appointment management system built for modern clinics.</p>
                <div class="trust-badges">
                    
                </div>
            </div>

        </div>
    </div>

    <!-- ══ RIGHT PANEL ══ -->
    <div class="panel-right">
        <div class="form-box">

            <!-- Logo -->
            <img src="/clinic_1/assets/images/logo.png" alt="Cryptalis Clinic" class="form-logo-img">

            <h1>Welcome back</h1>
            <p class="subtitle">Sign in to your Cryptalis Clinic account</p>

            <!-- Error message -->
            <?php if ($error): ?>
            <div class="alert-login">
                <i class="bi bi-exclamation-circle" style="flex-shrink:0"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Login form -->
            <form method="POST" id="loginForm" novalidate>

                <div class="mb-3">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control <?= $error ? 'is-invalid' : '' ?>"
                        placeholder="Enter your username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        autocomplete="username"
                        autofocus
                        required>
                </div>

                <div class="mb-1">
                    <label for="password">Password</label>
                    <div class="input-icon-group">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control <?= $error ? 'is-invalid' : '' ?>"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required>
                        <button type="button" class="eye-toggle" id="eyeToggle" tabindex="-1" aria-label="Show/hide password">
                            <i id="eyeIconShow" class="bi bi-eye"></i>
                            <i id="eyeIconHide" class="bi bi-eye-slash" style="display:none"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4 mt-1"></div>

                <button type="submit" class="btn btn-login w-100">
                    <i class="bi bi-box-arrow-in-right" style="margin-right:6px"></i>Log In
                </button>

            </form>

            <p class="form-footer mt-3">
                &copy; <?= date('Y') ?> Cryptalis Clinic &mdash; WOW AMAZING.
            </p>

        </div>
    </div><!-- /panel-right -->

</div><!-- /login-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Password show/hide toggle
    const eyeToggle   = document.getElementById('eyeToggle');
    const eyeIconShow = document.getElementById('eyeIconShow');
    const eyeIconHide = document.getElementById('eyeIconHide');
    const pwField     = document.getElementById('password');

    eyeToggle.addEventListener('click', () => {
        const isHidden = pwField.type === 'password';
        pwField.type          = isHidden ? 'text' : 'password';
        eyeIconShow.style.display = isHidden ? 'none' : '';
        eyeIconHide.style.display = isHidden ? '' : 'none';
    });
</script>
</body>
</html>
