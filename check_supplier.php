<?php
require_once 'config/db_connect.php';

echo "=== SUPPLIER TABLE STRUCTURE ===\n";
$result = $conn->query("DESCRIBE supplier");
if ($result) {
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Error describing table: " . $conn->error . "\n";
}

echo "\n=== SUPPLIER DATA ===\n";
$result = $conn->query("SELECT * FROM supplier LIMIT 5");
if ($result) {
    while($row = $result->fetch_assoc()) {
        print_r($row);
        echo "---\n";
    }
} else {
    echo "Error selecting data: " . $conn->error . "\n";
}
?>
