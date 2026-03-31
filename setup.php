<?php
include "config/db.php";

// Create users table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "✓ Users table created successfully!<br>";
} else {
    echo "✗ Error creating table: " . mysqli_error($conn) . "<br>";
}

// Check if admin user exists
$check = mysqli_query($conn, "SELECT * FROM users WHERE email='admin@example.com' LIMIT 1");
if (mysqli_num_rows($check) == 0) {
    // Insert default admin user (password: admin123)
    $hashed_password = password_hash("admin123", PASSWORD_DEFAULT);
    $insert = "INSERT INTO users (name, email, password) VALUES ('Admin User', 'admin@example.com', '$hashed_password')";
    
    if (mysqli_query($conn, $insert)) {
        echo "✓ Default admin user created!<br>";
        echo "<strong>Login Credentials:</strong><br>";
        echo "Email: admin@example.com<br>";
        echo "Password: admin123<br><br>";
    } else {
        echo "✗ Error creating admin user: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "✓ Admin user already exists<br>";
}

echo "<br><a href='login.php' class='btn btn-primary'>Go to Login</a>";
?>
