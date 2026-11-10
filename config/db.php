<?php

$host = "localhost";
$username = "root";
$password = "energy";
$database = "accounting_db";
//$database = "tooffice";

// Create connection
$conn = @mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    // Fallback to empty password if energy fails
    $conn = @mysqli_connect($host, $username, "", $database);
}

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Optional: Set charset
mysqli_set_charset($conn, "utf8");

?>