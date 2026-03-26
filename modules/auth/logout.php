<?php
// ============================================================
// modules/auth/logout.php — Logout Handler
// Called when any user clicks "Logout" in the sidebar or topbar.
// Loads auth.php (which starts the session), then calls logoutUser()
// which: (1) clears all session variables, (2) destroys the session,
// (3) sets no-cache headers so back button won't restore the page,
// (4) redirects to login. — Covers Bonus 3.
// ============================================================
require_once '../../includes/auth.php';
logoutUser(); // See includes/auth.php — handles full secure logout
?>