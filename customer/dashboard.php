<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is customer
if ($_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit;
}

// Get customer information
$customerID = $_SESSION['user_id'];
$customer = null;
$query = "SELECT * FROM customer WHERE CustomerID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $customerID);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $customer = $result->fetch_assoc();
}

// Get recent orders
$recentOrders = [];
$query = "SELECT o.*, COUNT(od.OrderDetailID) as ItemCount 
          FROM orders o 
          JOIN order_details od ON o.OrderID = od.OrderID 
          WHERE o.CustomerID = ? 
          GROUP BY o.OrderID 
          ORDER BY o.OrderDate DESC 
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $customerID);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentOrders[] = $row;
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
            <a class="nav-link active" href="dashboard.php">
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
            <div class="col-md-12">
                <h2 class="mb-0">Customer Dashboard</h2>
                <p class="text-muted">Welcome back, <?php echo $customer ? $customer['CustomerName'] : 'Customer'; ?>!</p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card dashboard-card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <a href="order.php" class="btn btn-primary btn-lg w-100 h-100 d-flex flex-column justify-content-center align-items-center p-4">
                                    <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                    <span>Place Order</span>
                                </a>
                            </div>
                            <div class="col-6 mb-3">
                                <a href="orders.php" class="btn btn-outline-primary btn-lg w-100 h-100 d-flex flex-column justify-content-center align-items-center p-4">
                                    <i class="fas fa-list-alt fa-2x mb-2"></i>
                                    <span>View Orders</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="profile.php" class="btn btn-outline-primary btn-lg w-100 h-100 d-flex flex-column justify-content-center align-items-center p-4">
                                    <i class="fas fa-user fa-2x mb-2"></i>
                                    <span>My Profile</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="support.php" class="btn btn-outline-primary btn-lg w-100 h-100 d-flex flex-column justify-content-center align-items-center p-4">
                                    <i class="fas fa-headset fa-2x mb-2"></i>
                                    <span>Support</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card dashboard-card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Account Information</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($customer): ?>
                        <div class="mb-3">
                            <label class="form-label text-muted">Name</label>
                            <p class="mb-0"><?php echo $customer['CustomerName']; ?></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Phone</label>
                            <p class="mb-0"><?php echo $customer['CustomerNumber']; ?></p>
                        </div>
                        <div>
                            <label class="form-label text-muted">Address</label>
                            <p class="mb-0"><?php echo $customer['CustomerAddress']; ?></p>
                        </div>
                        <div class="mt-3">
                            <a href="profile.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit me-1"></i> Edit Profile
                            </a>
                        </div>
                        <?php else: ?>
                        <p class="text-center">Customer information not available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card dashboard-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent Orders</h5>
                        <a href="orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($recentOrders) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><?php echo $order['OrderID']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['OrderDate'])); ?></td>
                                        <td><?php echo $order['ItemCount']; ?> items</td>
                                        <td>₱<?php echo number_format($order['Subtotal'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $order['OrderStatus'] == 'Completed' ? 'success' : 'warning'; ?>">
                                                <?php echo $order['OrderStatus']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="order_details.php?id=<?php echo $order['OrderID']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                            <p class="mb-0">You haven't placed any orders yet.</p>
                            <a href="order.php" class="btn btn-primary mt-3">Place Your First Order</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
