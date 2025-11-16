<?php
require_once 'config/db_connect.php';

echo "Adding Email and Address columns to supplier table...\n";

// Add Email column
$result1 = $conn->query("ALTER TABLE supplier ADD COLUMN Email VARCHAR(100) DEFAULT NULL");
if ($result1) {
    echo "Email column added successfully.\n";
} else {
    echo "Error adding Email column (might already exist): " . $conn->error . "\n";
}

// Add Address column
$result2 = $conn->query("ALTER TABLE supplier ADD COLUMN Address VARCHAR(255) DEFAULT NULL");
if ($result2) {
    echo "Address column added successfully.\n";
} else {
    echo "Error adding Address column (might already exist): " . $conn->error . "\n";
}

// Update existing suppliers with sample data
$updates = [
    ['SupplierID' => 'Sup-00001', 'Email' => 'contact@cocacola.com', 'Address' => '123 Beverage St, Manila'],
    ['SupplierID' => 'Sup-00002', 'Email' => 'info@pepsico.com', 'Address' => '456 Soda Ave, Quezon City'],
    ['SupplierID' => 'Sup-00003', 'Email' => 'sales@wilkins.com', 'Address' => '789 Water Blvd, Makati']
];

foreach ($updates as $update) {
    $stmt = $conn->prepare("UPDATE supplier SET Email = ?, Address = ? WHERE SupplierID = ?");
    $stmt->bind_param('sss', $update['Email'], $update['Address'], $update['SupplierID']);
    if ($stmt->execute()) {
        echo "Updated supplier " . $update['SupplierID'] . " with email and address.\n";
    } else {
        echo "Error updating supplier " . $update['SupplierID'] . ": " . $conn->error . "\n";
    }
}

echo "Supplier table update completed!\n";
?>
