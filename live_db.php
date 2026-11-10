<?php

$host = "localhost";
$username = "accuser";
$password = "Acc_System#2026!";
$database = "accounting_db";

// Create connection
$conn = @mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8");

?>
