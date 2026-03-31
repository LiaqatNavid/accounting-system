<?php
include "config/db.php";

// Create roles table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "✓ Roles table created successfully!<br>";
} else {
    echo "✗ Error creating roles table: " . mysqli_error($conn) . "<br>";
}

// Add role_id column to users table if it doesn't exist
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'role_id'");

if (mysqli_num_rows($check_column) == 0) {
    $alter_sql = "ALTER TABLE users ADD COLUMN role_id INT DEFAULT NULL AFTER password";
    
    if (mysqli_query($conn, $alter_sql)) {
        echo "✓ Added role_id column to users table!<br>";
    } else {
        echo "✗ Error adding role_id column: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "✓ role_id column already exists in users table<br>";
}

// Insert sample roles
$roles = [
    ['Admin', 'Administrator with full access'],
    ['Manager', 'Manager with limited access'],
    ['User', 'Regular user with basic access']
];

foreach ($roles as $role) {
    $check = mysqli_query($conn, "SELECT * FROM roles WHERE role_name='{$role[0]}'");
    
    if (mysqli_num_rows($check) == 0) {
        $insert = "INSERT INTO roles (role_name, description) VALUES ('{$role[0]}', '{$role[1]}')";
        mysqli_query($conn, $insert);
        echo "✓ Added role: {$role[0]}<br>";
    }
}

echo "<br><h3>Setup Complete!</h3>";
echo "<p><a href='admin/roles.php' class='btn btn-primary'>Go to Roles Management</a></p>";
echo "<p><a href='admin/user_roles.php' class='btn btn-primary'>Go to User Roles Assignment</a></p>";
?>
