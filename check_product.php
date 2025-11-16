<?php
require_once 'config/db_connect.php';
$result = $conn->query('DESCRIBE product');
echo "Product table structure:\n";
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>
