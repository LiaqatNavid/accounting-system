<?php

// Disable throwing exceptions so fallback logic works smoothly across PHP versions
mysqli_report(MYSQLI_REPORT_OFF);

// Try live cPanel hosting credentials first if available
$conn = @mysqli_connect("localhost", "accuser", "Acc_System#2026!", "accounting_db");

if (!$conn) {
    // Try local credentials
    $conn = @mysqli_connect("localhost", "root", "energy", "accounting_db");
}

if (!$conn) {
    // Fallback to local empty password
    $conn = @mysqli_connect("localhost", "root", "", "accounting_db");
}

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8");

?>
