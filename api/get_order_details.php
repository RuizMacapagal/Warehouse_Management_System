<?php
// Include database connection
require_once '../config/db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if order_id is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

$order_id = mysqli_real_escape_string($conn, $_GET['order_id']);

// Remove # symbol if present
$order_id = str_replace('#', '', $order_id);

// Get order information with proper field names
$orderQuery = "SELECT o.OrderID, o.CustomerID, o.OrderDate, o.OrderStatus, o.Subtotal as TotalAmount,
               c.CustomerName, c.CustomerEmail, c.CustomerNumber, c.CustomerAddress, 
               u.username 
               FROM orders o 
               LEFT JOIN customer c ON o.CustomerID = c.CustomerID 
               LEFT JOIN users u ON o.AdminID = u.id 
               WHERE o.OrderID = '$order_id'";

$orderResult = mysqli_query($conn, $orderQuery);

if (!$orderResult || mysqli_num_rows($orderResult) == 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$orderData = mysqli_fetch_assoc($orderResult);

// Get order details
$detailsQuery = "SELECT od.*, p.ProductName, p.QtyPerCase 
                FROM order_details od 
                JOIN product p ON od.ProductID = p.ProductID 
                WHERE od.OrderID = '$order_id'";

$detailsResult = mysqli_query($conn, $detailsQuery);

if (!$detailsResult) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch order details']);
    exit;
}

$orderItems = [];
$totalAmount = 0;

while ($detail = mysqli_fetch_assoc($detailsResult)) {
    $orderItems[] = [
        'product_name' => $detail['ProductName'],
        'price' => $detail['ProductPrice'],
        'quantity' => $detail['QuantityOrdered'],
        'total' => $detail['ProductPrice'] * $detail['QuantityOrdered']
    ];
    $totalAmount += ($detail['QuantityOrdered'] * $detail['ProductPrice']);
}

// Format order data to match frontend expectations
$formattedOrder = [
    'id' => $orderData['OrderID'],
    'customer_name' => $orderData['CustomerName'] ?? 'Walk-in Customer',
    'order_date' => $orderData['OrderDate'],
    'order_status' => $orderData['OrderStatus'],
    'total_amount' => $orderData['TotalAmount'] ?? $totalAmount
];

// Prepare response
$response = [
    'success' => true,
    'order' => $formattedOrder,
    'items' => $orderItems,
    'totalAmount' => $totalAmount
];

echo json_encode($response);
?>