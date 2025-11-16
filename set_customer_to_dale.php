<?php
// Connect to the database
require_once 'config/db_connect.php';

// Update all Coca-Cola products to have 12 pieces per case
$updateQuery = "UPDATE product SET QtyPerCase = 12 WHERE ProductName LIKE '%Coca%Cola%' OR ProductName LIKE '%Coke%'";
$updateResult = mysqli_query($conn, $updateQuery);

if ($updateResult) {
    echo "Successfully updated Coca-Cola products to 12 pieces per case.<br>";
} else {
    echo "Error updating Coca-Cola products: " . mysqli_error($conn) . "<br>";
}

// Find products with different QtyPerCase values for review
$reviewQuery = "SELECT ProductID, ProductName, QtyPerCase FROM product WHERE QtyPerCase != 12";
$reviewResult = mysqli_query($conn, $reviewQuery);

echo "<h3>Products with QtyPerCase different from 12:</h3>";
echo "<table border='1'>";
echo "<tr><th>Product ID</th><th>Product Name</th><th>Quantity Per Case</th></tr>";

if (mysqli_num_rows($reviewResult) > 0) {
    while ($row = mysqli_fetch_assoc($reviewResult)) {
        echo "<tr>";
        echo "<td>" . $row['ProductID'] . "</td>";
        echo "<td>" . $row['ProductName'] . "</td>";
        echo "<td>" . $row['QtyPerCase'] . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='3'>All products have 12 pieces per case.</td></tr>";
}

echo "</table>";

// Close the connection
mysqli_close($conn);
?>
