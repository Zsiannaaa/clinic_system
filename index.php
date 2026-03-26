<?php
// ============================================================
// index.php — Root Entry Point
// This is the very first file that loads when someone visits
// http://localhost/clinic_1/
// It does nothing except immediately redirect the browser
// to the login page. No one can skip login from here.
// ============================================================
header("Location: modules/auth/login.php");
exit(); // Always call exit() after a redirect so no extra code runs
?>