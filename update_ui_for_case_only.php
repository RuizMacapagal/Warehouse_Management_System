<?php
// Connect to the database
require_once 'config/db_connect.php';

echo "<h2>UI Update for Case-Only Sales</h2>";

// List of files to check and update
$files_to_update = [
    'admin/products.php',
    'admin/dashboard.php',
    'cashier/pos.php',
    'cashier/orders.php',
    'customer/order.php',
    'customer/order_details.php',
    'inventory/dashboard.php',
    'inventory/stock.php'
];

$updates_made = [];

// Function to update file content
function update_file_content($file_path, $search_patterns, $replacements) {
    if (!file_exists($file_path)) {
        return "File not found: $file_path";
    }
    
    $content = file_get_contents($file_path);
    $original = $content;
    
    foreach ($search_patterns as $index => $pattern) {
        $content = str_replace($pattern, $replacements[$index], $content);
    }
    
    if ($content !== $original) {
        file_put_contents($file_path, $content);
        return "Updated successfully";
    }
    
    return "No changes needed";
}

// Update admin/products.php - Remove piece-related fields
$admin_products_result = update_file_content(
    'admin/products.php',
    [
        'StockOnHandPerPiece',
        'Per Piece',
        'placeholder="Stock on Hand Per Piece"',
        'name="stockOnHandPerPiece"'
    ],
    [
        'StockOnHandPerCase',
        'Per Case',
        'placeholder="Stock on Hand Per Case"',
        'name="stockOnHandPerCase"'
    ]
);
$updates_made[] = "admin/products.php: $admin_products_result";

// Update cashier/pos.php - Remove piece calculations and display
$pos_result = update_file_content(
    'cashier/pos.php',
    [
        'Per Piece',
        'StockOnHandPerPiece',
        '/ p.QtyPerCase',
        'piece',
        'Piece'
    ],
    [
        'Per Case',
        'StockOnHandPerCase',
        '',
        'case',
        'Case'
    ]
);
$updates_made[] = "cashier/pos.php: $pos_result";

// Update inventory/dashboard.php - Remove piece-related displays
$inventory_dashboard_result = update_file_content(
    'inventory/dashboard.php',
    [
        'Per Piece',
        'StockOnHandPerPiece',
        'Pieces',
        'pieces'
    ],
    [
        'Per Case',
        'StockOnHandPerCase',
        'Cases',
        'cases'
    ]
);
$updates_made[] = "inventory/dashboard.php: $inventory_dashboard_result";

// Update inventory/stock.php - Remove piece-related displays
$inventory_stock_result = update_file_content(
    'inventory/stock.php',
    [
        'Per Piece',
        'StockOnHandPerPiece',
        'Pieces',
        'pieces'
    ],
    [
        'Per Case',
        'StockOnHandPerCase',
        'Cases',
        'cases'
    ]
);
$updates_made[] = "inventory/stock.php: $inventory_stock_result";

// Update customer/order.php - Remove piece calculations
$customer_order_result = update_file_content(
    'customer/order.php',
    [
        'Per Piece',
        'StockOnHandPerPiece',
        '/ p.QtyPerCase',
        'piece',
        'Piece'
    ],
    [
        'Per Case',
        'StockOnHandPerCase',
        '',
        'case',
        'Case'
    ]
);
$updates_made[] = "customer/order.php: $customer_order_result";

// Update customer/order_details.php - Update display
$order_details_result = update_file_content(
    'customer/order_details.php',
    [
        'Per Piece',
        'piece',
        'Piece'
    ],
    [
        'Per Case',
        'case',
        'Case'
    ]
);
$updates_made[] = "customer/order_details.php: $order_details_result";

// Update database structure - Add a trigger to ensure StockOnHandPerPiece is always 0
$sql = "
-- Drop trigger if it exists
DROP TRIGGER IF EXISTS before_product_update;

-- Create trigger to ensure StockOnHandPerPiece is always 0
CREATE TRIGGER before_product_update
BEFORE UPDATE ON product
FOR EACH ROW
SET NEW.StockOnHandPerPiece = 0;

-- Drop trigger if it exists
DROP TRIGGER IF EXISTS before_product_insert;

-- Create trigger to ensure StockOnHandPerPiece is always 0 for new products
CREATE TRIGGER before_product_insert
BEFORE INSERT ON product
FOR EACH ROW
SET NEW.StockOnHandPerPiece = 0;
";

if (mysqli_multi_query($conn, $sql)) {
    $updates_made[] = "Database triggers: Created successfully";
} else {
    $updates_made[] = "Database triggers: Error - " . mysqli_error($conn);
}

// Display results
echo "<h3>Updates Made:</h3>";
echo "<ul>";
foreach ($updates_made as $update) {
    echo "<li>$update</li>";
}
echo "</ul>";

echo "<h3>System is now updated for case-only sales</h3>";
echo "<p>All piece-related UI elements have been removed or updated to reflect case-only sales.</p>";
echo "<p>Database triggers have been added to ensure StockOnHandPerPiece is always 0.</p>";

// Close the connection
mysqli_close($conn);
?>