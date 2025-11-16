<?php
// Connect to the database
require_once 'config/db_connect.php';

// Current market prices in the Philippines (based on research)
$prices = [
    // Coca-Cola products
    'Coke 250ml' => 25.00,
    'Coke 500ml' => 35.00,
    'Coke 1L' => 60.00,
    
    // Royal products
    'Royal 250ml' => 25.00,
    'Royal 500ml' => 35.00,
    'Royal 1L' => 60.00,
    
    // San Miguel Beer products
    'San Miguel Beer Light' => 45.00,
    'San Miguel Beer Strong' => 50.00,
    'San Miguel Beer Regular' => 45.00
];

echo "<h2>Product Price Update</h2>";
echo "<p>Updating prices to match current Philippine market rates...</p>";

// Update each product price
foreach ($prices as $productName => $newPrice) {
    $updateQuery = "UPDATE product SET ProductPrice = $newPrice WHERE ProductName LIKE '%$productName%'";
    $updateResult = mysqli_query($conn, $updateQuery);
    
    if ($updateResult) {
        echo "<p>✓ Updated price for '$productName' to ₱$newPrice</p>";
    } else {
        echo "<p>✗ Error updating price for '$productName': " . mysqli_error($conn) . "</p>";
    }
}

// Display updated product list
$productsQuery = "SELECT ProductID, ProductName, ProductPrice, ProductCategory FROM product ORDER BY ProductCategory, ProductName";
$productsResult = mysqli_query($conn, $productsQuery);

echo "<h3>Updated Product Prices</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f2f2f2;'>";
echo "<th style='padding: 8px; text-align: left;'>Product ID</th>";
echo "<th style='padding: 8px; text-align: left;'>Product Name</th>";
echo "<th style='padding: 8px; text-align: left;'>Category</th>";
echo "<th style='padding: 8px; text-align: left;'>Price (₱)</th>";
echo "</tr>";

if (mysqli_num_rows($productsResult) > 0) {
    while ($row = mysqli_fetch_assoc($productsResult)) {
        echo "<tr>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $row['ProductID'] . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $row['ProductName'] . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $row['ProductCategory'] . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>₱" . number_format($row['ProductPrice'], 2) . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='4' style='padding: 8px; border: 1px solid #ddd;'>No products found.</td></tr>";
}

echo "</table>";

// Close the connection
mysqli_close($conn);
?>