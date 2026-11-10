<?php

$host = "localhost";
$username = "root";
$password = "energy";
$database = "accounting_db";

// Try local connection first
$conn = @mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    // Try live cPanel hosting credentials
    $conn = @mysqli_connect("localhost", "accuser", "Acc_System#2026!", "accounting_db");
}
if (!$conn) {
    // Fallback to local empty password
    $conn = @mysqli_connect($host, $username, "", $database);
}

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Optional: Set charset
mysqli_set_charset($conn, "utf8");

?>
