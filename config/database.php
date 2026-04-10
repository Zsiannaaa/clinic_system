<?php
require_once __DIR__ . '/env.php';

// ============================================================
// config/database.php - Database Connection
//
// This file runs ONCE and creates the $pdo object.
// Every module includes this file (via auth.php) to get
// access to the database.
// ============================================================

$host     = (string) env('DB_HOST', 'localhost');
$dbname   = (string) env('DB_NAME', 'clinic_1');
$username = (string) env('DB_USER', 'root');
$password = (string) env('DB_PASS', '');
$port     = (string) env('DB_PORT', '');

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    if ($port !== '') {
        $dsn .= ";port=$port";
    }

    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if ((string) env('APP_ENV', 'local') === 'local') {
        die('Database Connection Failed: ' . $e->getMessage());
    }

    die('Database connection failed. Please check server configuration.');
}
?>
