<?php
set_time_limit(300);
ini_set('memory_limit', '512M');

$conn = new mysqli("localhost", "accuser", "Acc_System#2026!", "accounting_db");
if ($conn->connect_error) {
    die("Connection error: " . $conn->connect_error);
}

$file = "/home/liaqatna/accounting_db_export.sql";
if (!file_exists($file)) {
    die("File not found: " . $file);
}

$sql = file_get_contents($file);
$queries = explode(";\n", $sql);
$success = 0;
$errors = [];

foreach ($queries as $q) {
    $q = trim($q);
    if (!empty($q)) {
        if ($conn->query($q)) {
            $success++;
        } else {
            $errors[] = $conn->error;
        }
    }
}

echo "IMPORT DONE: $success queries executed. Errors: " . count($errors) . "\n";
if (count($errors) > 0) {
    echo "First error: " . $errors[0] . "\n";
}
$conn->close();
?>
