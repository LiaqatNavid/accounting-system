<?php
include "config/db.php";

// Create chart_of_account table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS chart_of_account (
    RECORD_ID INT AUTO_INCREMENT PRIMARY KEY,
    ACCOUNT_NO VARCHAR(50) UNIQUE NOT NULL,
    ACCOUNT_DESCRIPTION VARCHAR(255) NOT NULL,
    CLASS ENUM('Assets', 'Expense', 'Liability', 'Revenue') NOT NULL,
    STATUS ENUM('Control', 'Detail') NOT NULL,
    RECORDDATE TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CLASS_CODE VARCHAR(50),
    CONTROL_LEVEL INT DEFAULT 1,
    ACC_STATUS ENUM('ACTIVE', 'PASSIVE') DEFAULT 'ACTIVE',
    INDEX idx_account_no (ACCOUNT_NO),
    INDEX idx_class (CLASS),
    INDEX idx_status (ACC_STATUS)
)";

if (mysqli_query($conn, $sql)) {
    echo "✓ Chart of Account table created successfully!<br>";
} else {
    echo "✗ Error creating table: " . mysqli_error($conn) . "<br>";
}

echo "<br><h3>Setup Complete!</h3>";
echo "<p><a href='admin/chartofaccount/chartofaccount.php' class='btn btn-primary'>Go to Chart of Accounts</a></p>";
?>
