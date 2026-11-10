<?php
$conn = new mysqli("localhost", "accuser", "Acc_System#2026!", "accounting_db");
if ($conn->connect_error) {
    die("Connection error: " . $conn->connect_error);
}
$res = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $res->fetch_array()) {
    $tables[] = $row[0];
}
echo "TABLES COUNT: " . count($tables) . "\n";
echo implode(", ", $tables);
$conn->close();
?>
