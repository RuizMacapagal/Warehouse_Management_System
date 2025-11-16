<?php
// Connect to the database
require_once 'config/db_connect.php';

// Update all products to have 12 pieces per case
$updateQuery = "UPDATE product SET QtyPerCase = 12";
$updateResult = mysqli_query($conn, $updateQuery);

if ($updateResult) {
    echo "<h3>System Update Complete</h3>";
    echo "<p>Successfully updated all products to 12 pieces per case.</p>";
    
    // Update inventory calculations
    $recalculateQuery = "UPDATE product SET StockOnHandPerPiece = StockOnHandPerCase * 12";
    $recalculateResult = mysqli_query($conn, $recalculateQuery);
    
    if ($recalculateResult) {
        echo "<p>Successfully recalculated stock on hand per piece for all products.</p>";
    } else {
        echo "<p>Error recalculating stock: " . mysqli_error($conn) . "</p>";
    }
} else {
    echo "<h3>Update Failed</h3>";
    echo "<p>Error updating products: " . mysqli_error($conn) . "</p>";
}

// Find any products that might need special attention
$reviewQuery = "SELECT ProductID, ProductName, ProductCategory, QtyPerCase, StockOnHandPerCase, StockOnHandPerPiece FROM product ORDER BY ProductCategory, ProductName";
$reviewResult = mysqli_query($conn, $reviewQuery);

echo "<h3>Updated Product Inventory</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f2f2f2;'>";
echo "<th style='padding: 8px; text-align: left;'>Product ID</th>";
echo "<th style='padding: 8px; text-align: left;'>Product Name</th>";
echo "<th style='padding: 8px; text-align: left;'>Category</th>";
echo "<th style='padding: 8px; text-align: left;'>Qty Per Case</th>";
echo "<th style='padding: 8px; text-align: left;'>Cases in Stock</th>";
echo "<th style='padding: 8px; text-align: left;'>Pieces in Stock</th>";
echo "</tr>";

if (mysqli_num_rows($reviewResult) > 0) {
    while ($row = mysqli_fetch_assoc($reviewResult)) {
        echo "<tr>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $row['ProductID'] . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $row['ProductName'] . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $row['ProductCategory'] . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $row['QtyPerCase'] . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $row['StockOnHandPerCase'] . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $row['StockOnHandPerPiece'] . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6' style='padding: 8px; border: 1px solid #ddd;'>No products found.</td></tr>";
}

echo "</table>";

// Close the connection
mysqli_close($conn);
?>