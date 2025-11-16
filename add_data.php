<?php
// Connect to database
require_once 'config/db_connect.php';

// Customer was already added successfully
echo "Customer Dale Hidalgo was already added successfully.\n";

// Add products in different sizes with correct column names
$products = [
    ['Coca-Cola 250ml', 'Beverage', 25.00, 100, 10],
    ['Coca-Cola 500ml', 'Beverage', 35.00, 100, 10],
    ['Coca-Cola 1L', 'Beverage', 60.00, 100, 10],
    ['Royal 250ml', 'Beverage', 25.00, 100, 10],
    ['Royal 500ml', 'Beverage', 35.00, 100, 10],
    ['Royal 1L', 'Beverage', 60.00, 100, 10],
    ['Sprite 250ml', 'Beverage', 25.00, 100, 10],
    ['Sprite 500ml', 'Beverage', 35.00, 100, 10],
    ['Sprite 1L', 'Beverage', 60.00, 100, 10],
    ['Wilkins Water 350ml', 'Beverage', 20.00, 100, 10],
    ['Wilkins Water 500ml', 'Beverage', 25.00, 100, 10],
    ['Wilkins Water 1L', 'Beverage', 40.00, 100, 10],
    ['San Miguel Beer Regular', 'Beverage', 45.00, 100, 10],
    ['San Miguel Beer Light', 'Beverage', 45.00, 100, 10],
    ['San Miguel Beer Strong', 'Beverage', 50.00, 100, 10]
];

foreach ($products as $product) {
    $productId = 'P-' . date('YmdHis') . '-' . rand(100, 999);
    $name = $product[0];
    $category = $product[1];
    $price = $product[2];
    $stock = $product[3];
    $reorderPoint = $product[4];
    $status = 'Available';
    
    $stmt = $conn->prepare("INSERT INTO product (ProductID, ProductName, ProductCategory, ProductPrice, ProductStatus, StockOnHandPerCase, ReorderPoint) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssdsii', $productId, $name, $category, $price, $status, $stock, $reorderPoint);
    
    if ($stmt->execute()) {
        echo "Product added: $name\n";
    } else {
        echo "Error adding product $name: " . $conn->error . "\n";
    }
    
    // Add a small delay to ensure unique IDs
    usleep(100000);
}

echo "All operations completed.";
?>
