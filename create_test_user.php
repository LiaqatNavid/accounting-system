<?php
include "config/db.php";

// Create test user
$name = "Test User";
$email = "test@example.com";
$password = password_hash("test123", PASSWORD_DEFAULT);

// Insert into user table
$query = "INSERT INTO user (name, email, password) VALUES ('$name', '$email', '$password')";

if (mysqli_query($conn, $query)) {
    echo "✓ Test user created successfully!<br><br>";
    echo "<strong>Login Credentials:</strong><br>";
    echo "Email: test@example.com<br>";
    echo "Password: test123<br><br>";
    echo "<a href='login.php' class='btn btn-primary'>Go to Login</a>";
} else {
    // Check if user already exists
    if (strpos(mysqli_error($conn), "Duplicate entry") !== false) {
        echo "✓ Test user already exists!<br><br>";
        echo "<strong>Login Credentials:</strong><br>";
        echo "Email: test@example.com<br>";
        echo "Password: test123<br><br>";
        echo "<a href='login.php' class='btn btn-primary'>Go to Login</a>";
    } else {
        echo "✗ Error creating user: " . mysqli_error($conn) . "<br>";
    }
}
?>
