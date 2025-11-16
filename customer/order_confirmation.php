<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is customer
if ($_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit;
}

// Get order details
$orderID = isset($_GET['order_id']) ? $_GET['order_id'] : '';
$order = null;
$orderItems = [];

if ($orderID) {
    // Get order information
    $query = "SELECT o.*, c.CustomerName FROM orders o JOIN customer c ON o.CustomerID = c.CustomerID WHERE o.OrderID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $orderID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $order = $result->fetch_assoc();
        
        // Get order items
        $query = "SELECT od.*, p.ProductName, p.ProductCategory 
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
        <h3><i class="fas fa-store"></i> 8JJ's Trading Incorporation</h3>
        <p>Customer Panel</p>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="order.php">
                <i class="fas fa-shopping-cart"></i> Place Order
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="orders.php">
                <i class="fas fa-list-alt"></i> My Orders
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="profile.php">
                <i class="fas fa-user"></i> My Profile
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
                <h2 class="mb-0">Order Confirmation</h2>
                <div>
                    <a href="order.php" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i> Place New Order
                    </a>
                </div>
            </div>
        </div>

        <?php if ($order): ?>
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card dashboard-card">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <div class="mb-3">
                                <i class="fas fa-check-circle text-success fa-5x"></i>
                            </div>
                            <h3>Thank You for Your Order!</h3>
                            <p class="text-muted">Your order has been received and is being processed.</p>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="mb-2">Order Details</h5>
                                <p class="mb-1"><strong>Order ID:</strong> <?php echo $orderID; ?></p>
                                <p class="mb-1"><strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['OrderDate'])); ?></p>
                                <p class="mb-0"><strong>Status:</strong> 
                                    <span class="badge bg-warning"><?php echo $order['OrderStatus']; ?></span>
                                </p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <h5 class="mb-2">Customer</h5>
                                <p class="mb-0"><strong>Name:</strong> <?php echo $order['CustomerName']; ?></p>
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
                                        <td class="text-center"><?php echo $item['QuantityOrdered']; ?></td>
                                        <td class="text-end">₱<?php echo number_format($item['ProductPrice'] * $item['QuantityOrdered'], 2); ?></td>
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
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Your order is now pending approval. You will be notified once it's processed.
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="orders.php" class="btn btn-outline-primary">
                                <i class="fas fa-list-alt me-2"></i> View All Orders
                            </a>
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

<?php include '../includes/footer.php'; ?>
