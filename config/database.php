<?php
// ============================================================
// config/database.php — Database Connection
//
// This file runs ONCE and creates the $pdo object.
// Every module includes this file (via auth.php) to get
// access to the database.
//
// WHY PDO? It uses prepared statements which prevent SQL Injection.
// The '?' placeholders in queries are never concatenated with user
// input — the database driver handles them safely.
//
// WHY ATTR_ERRMODE_EXCEPTION? So any failed query throws a PHP
// exception instead of silently failing — easier to catch bugs.
// ============================================================
$host     = 'localhost';       // MySQL server (same machine in XAMPP)
$dbname   = 'clinic_1';       // The database name we created in setup.sql
$username = 'root';            // Default XAMPP MySQL username
$password = '';                // Default XAMPP MySQL password (empty)

try {
    // DSN = Data Source Name: tells PDO what driver and database to use
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

    // Make PDO throw exceptions on errors (not just return false silently)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Make all fetch() calls return associative arrays (e.g. $row['name'])
    // instead of numeric arrays (e.g. $row[0])
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If connection fails, stop everything and show the error
    // In production you'd log this and show a friendly error page
    die("❌ Database Connection Failed: " . $e->getMessage());
}
?>