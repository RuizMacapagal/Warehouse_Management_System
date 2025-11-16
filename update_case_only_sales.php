<?php
// Connect to the database
require_once 'config/db_connect.php';

echo "<h2>System Update: Case-Only Sales</h2>";

// 1. Update product prices for case-only sales
$updatePricesQuery = "UPDATE product SET 
    ProductPrice = CASE 
        WHEN ProductName LIKE '%Coke 250ml%' THEN 300.00
        WHEN ProductName LIKE '%Coke 500ml%' THEN 420.00
        WHEN ProductName LIKE '%Coke 1L%' THEN 720.00
        WHEN ProductName LIKE '%Royal 250ml%' THEN 300.00
        WHEN ProductName LIKE '%Royal 500ml%' THEN 420.00
        WHEN ProductName LIKE '%Royal 1L%' THEN 720.00
        WHEN ProductName LIKE '%San Miguel Beer Light%' THEN 540.00
        WHEN ProductName LIKE '%San Miguel Beer Strong%' THEN 600.00
        WHEN ProductName LIKE '%San Miguel Beer Regular%' THEN 540.00
        ELSE ProductPrice * 12
    END";

$updatePricesResult = mysqli_query($conn, $updatePricesQuery);

if ($updatePricesResult) {
    echo "<p>✓ Successfully updated product prices for case-only sales</p>";
} else {
    echo "<p>✗ Error updating product prices: " . mysqli_error($conn) . "</p>";
}

// 2. Set StockOnHandPerPiece to 0 as we don't sell individual pieces
$updateStockQuery = "UPDATE product SET StockOnHandPerPiece = 0";
$updateStockResult = mysqli_query($conn, $updateStockQuery);

if ($updateStockResult) {
    echo "<p>✓ Successfully updated stock to reflect case-only sales</p>";
} else {
    echo "<p>✗ Error updating stock: " . mysqli_error($conn) . "</p>";
}

// Display updated product list
$productsQuery = "SELECT ProductID, ProductName, ProductPrice, ProductCategory, StockOnHandPerCase, QtyPerCase FROM product ORDER BY ProductCategory, ProductName";
$productsResult = mysqli_query($conn, $productsQuery);

echo "<h3>Updated Product Prices (Case-Only Sales)</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f2f2f2;'>";
echo "<th style='padding: 8px; text-align: left;'>Product ID</th>";
echo "<th style='padding: 8px; text-align: left;'>Product Name</th>";
echo "<th style='padding: 8px; text-align: left;'>Category</th>";
echo "<th style='padding: 8px; text-align: left;'>Price Per Case (₱)</th>";
echo "<th style='padding: 8px; text-align: left;'>Qty Per Case</th>";
echo "<th style='padding: 8px; text-align: left;'>Cases in Stock</th>";
echo "</tr>";

if (mysqli_num_rows($productsResult) > 0) {
    while ($row = mysqli_fetch_assoc($productsResult)) {
        echo "<tr>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $row['ProductID'] . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $row['ProductName'] . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $row['ProductCategory'] . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>₱" . number_format($row['ProductPrice'], 2) . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $row['QtyPerCase'] . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $row['StockOnHandPerCase'] . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6' style='padding: 8px; border: 1px solid #ddd;'>No products found.</td></tr>";
}

echo "</table>";

// Close the connection
mysqli_close($conn);
?>