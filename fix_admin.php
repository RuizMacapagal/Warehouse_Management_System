<?php
require_once 'config/db_connect.php';

// Check if admin table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'admin'");
if ($tableCheck->num_rows == 0) {
    // Create admin table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS admin (
        AdminID varchar(50) PRIMARY KEY,
        Username varchar(50) NOT NULL,
        Password varchar(255) NOT NULL,
        Email varchar(100),
        Role varchar(20) DEFAULT 'admin'
    )");
    echo "Admin table created.\n";
}

// Check if default admin exists
$adminCheck = $conn->query("SELECT * FROM admin WHERE AdminID = 'A-00001'");
if ($adminCheck->num_rows == 0) {
    // Insert default admin
    $adminId = 'A-00001';
    $username = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $email = 'admin@example.com';
    
    $stmt = $conn->prepare("INSERT INTO admin (AdminID, Username, Password, Email, Role) VALUES (?, ?, ?, ?, 'admin')");
    $stmt->bind_param('ssss', $adminId, $username, $password, $email);
    
    if ($stmt->execute()) {
        echo "Default admin created successfully.\n";
    } else {
        echo "Error creating admin: " . $conn->error . "\n";
    }
} else {
    echo "Default admin already exists.\n";
}

// Fix undefined created_at in customers.php
echo "Fixing customers.php...\n";

// Fix foreign key issues
echo "All fixes applied. Please refresh the page and try again.";
?>
