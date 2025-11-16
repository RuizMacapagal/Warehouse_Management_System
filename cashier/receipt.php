<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is cashier
if ($_SESSION['role'] != 'cashier' && $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get order details
$orderID = isset($_GET['order_id']) ? $_GET['order_id'] : '';
$order = null;
$orderItems = [];
$customer = null;

if ($orderID) {
    // Get order information aligned with current schema
    $query = "SELECT o.OrderID, o.OrderDate, o.Subtotal, o.OrderStatus, 
                     c.CustomerName, c.CustomerAddress, c.CustomerNumber 
              FROM orders o 
              JOIN customer c ON o.CustomerID = c.CustomerID 
              WHERE o.OrderID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $orderID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $order = $result->fetch_assoc();
        
        // Get customer information
        $customer = [
            'name' => $order['CustomerName'],
            'address' => $order['CustomerAddress'],
            'phone' => $order['CustomerNumber']
        ];
        
        // Get order items aligned with current schema
        $query = "SELECT od.OrderDetailID, od.ProductID, od.QuantityOrdered, od.ProductPrice, 
                         p.ProductName, p.ProductCategory 
                  FROM order_details od 
                  JOIN product p ON od.ProductID = p.ProductID 
                  WHERE od.OrderID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $orderID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $orderItems[] = $row;
            }
        }
    }
}
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-cash-register"></i> 8JJ's Trading Incorporation</h3>
        <p>Cashier Panel</p>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="pos.php">
                <i class="fas fa-shopping-cart"></i> Point of Sale
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="orders.php">
                <i class="fas fa-list-alt"></i> Orders
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="customers.php">
                <i class="fas fa-users"></i> Customers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar"></i> Sales Reports
            </a>
        </li>
        <li class="nav-item mt-5">
            <a class="nav-link text-danger" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-12 d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Receipt</h2>
                <div>
                    <button class="btn btn-outline-primary me-2" id="print-receipt">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                    <a href="pos.php" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i> New Transaction
                    </a>
                </div>
            </div>
        </div>

        <?php if ($order): ?>
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card dashboard-card" id="receipt">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <h3 class="mb-0">Warehouse Management System</h3>
                            <p class="mb-0">123 Main Street, City, Country</p>
                            <p class="mb-0">Phone: (123) 456-7890</p>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="mb-2">Receipt</h5>
                                <p class="mb-1"><strong>Order ID:</strong> <?php echo $orderID; ?></p>
                                <p class="mb-1"><strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['OrderDate'])); ?></p>
                                <p class="mb-0"><strong>Cashier:</strong> <?php echo $_SESSION['username']; ?></p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <h5 class="mb-2">Customer</h5>
                                <p class="mb-1"><strong>Name:</strong> <?php echo $customer['name']; ?></p>
                                <p class="mb-1"><strong>Phone:</strong> <?php echo $customer['phone']; ?></p>
                                <p class="mb-0"><strong>Address:</strong> <?php echo $customer['address']; ?></p>
                            </div>
                        </div>
                        
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th>Category</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td><?php echo $item['ProductName']; ?></td>
                                        <td><?php echo $item['ProductCategory']; ?></td>
                                        <td class="text-end">₱<?php echo number_format($item['ProductPrice'], 2); ?></td>
                                        <td class="text-center"><?php echo (int)$item['QuantityOrdered']; ?></td>
                                        <td class="text-end">₱<?php echo number_format($item['ProductPrice'] * (int)$item['QuantityOrdered'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                        <td class="text-end">₱<?php echo number_format($order['Subtotal'] / 1.12, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Tax (12%):</strong></td>
                                        <td class="text-end">₱<?php echo number_format($order['Subtotal'] - ($order['Subtotal'] / 1.12), 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                        <td class="text-end">₱<?php echo number_format($order['Subtotal'], 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Payment Status:</strong> <?php echo $order['OrderStatus']; ?></p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p class="mb-0">Thank you for your purchase!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i> Order not found.
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Print receipt
    document.getElementById('print-receipt').addEventListener('click', function() {
        const receiptContent = document.getElementById('receipt').innerHTML;
        const originalContent = document.body.innerHTML;
        
        document.body.innerHTML = `
            <div style="max-width: 800px; margin: 0 auto; padding: 20px;">
                ${receiptContent}
            </div>
        `;
        
        window.print();
        document.body.innerHTML = originalContent;
        
        // Reattach event listener after restoring content
        document.getElementById('print-receipt').addEventListener('click', function() {
            window.print();
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
