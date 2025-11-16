<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is customer
if ($_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit;
}

$customerID = $_SESSION['user_id'];

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$dateFilter = $_GET['date_range'] ?? '';

// Build query with filters
$whereClause = "WHERE o.CustomerID = ?";
$params = [$customerID];
$types = 's';

if ($statusFilter) {
    $whereClause .= " AND o.OrderStatus = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($dateFilter) {
    switch ($dateFilter) {
        case 'today':
            $whereClause .= " AND DATE(o.OrderDate) = CURDATE()";
            break;
        case 'week':
            $whereClause .= " AND o.OrderDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $whereClause .= " AND o.OrderDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
}

// Get orders with details
$query = "SELECT o.*, COUNT(od.OrderDetailID) as ItemCount,
          SUM(od.QuantityOrdered) as TotalItems
          FROM orders o 
          LEFT JOIN order_details od ON o.OrderID = od.OrderID 
          $whereClause
          GROUP BY o.OrderID 
          ORDER BY o.OrderDate DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

// Get order statistics
$statsQuery = "SELECT 
    COUNT(*) as total_orders,
    COUNT(CASE WHEN OrderStatus = 'Pending' THEN 1 END) as pending_orders,
    COUNT(CASE WHEN OrderStatus = 'Completed' THEN 1 END) as completed_orders,
    COUNT(CASE WHEN OrderStatus = 'Cancelled' THEN 1 END) as cancelled_orders,
    COALESCE(SUM(Subtotal), 0) as total_spent
    FROM orders 
    WHERE CustomerID = ?";
$stmt = $conn->prepare($statsQuery);
$stmt->bind_param("s", $customerID);
$stmt->execute();
$statsResult = $stmt->get_result();
$stats = $statsResult->fetch_assoc();
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
            <a class="nav-link active" href="orders.php">
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
                <h2 class="mb-0">My Orders</h2>
                <p class="text-muted">Track and manage your order history</p>
            </div>
        </div>

        <!-- Order Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-shopping-bag fa-2x text-primary mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($stats['total_orders']); ?></h4>
                        <p class="text-muted mb-0">Total Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($stats['pending_orders']); ?></h4>
                        <p class="text-muted mb-0">Pending</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($stats['completed_orders']); ?></h4>
                        <p class="text-muted mb-0">Completed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-peso-sign fa-2x text-info mb-2"></i>
                        <h4 class="mb-1">₱<?php echo number_format($stats['total_spent'], 0); ?></h4>
                        <p class="text-muted mb-0">Total Spent</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card dashboard-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="Pending" <?php echo $statusFilter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Processing" <?php echo $statusFilter == 'Processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="Completed" <?php echo $statusFilter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo $statusFilter == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date Range</label>
                        <select name="date_range" class="form-select">
                            <option value="">All Time</option>
                            <option value="today" <?php echo $dateFilter == 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $dateFilter == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?php echo $dateFilter == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                        <a href="orders.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Orders List -->
        <div class="card dashboard-card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Order History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($orders)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-bag fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No orders found</h5>
                        <p class="text-muted">You haven't placed any orders yet or no orders match your filters.</p>
                        <a href="order.php" class="btn btn-primary">Place Your First Order</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['OrderID']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($order['OrderDate'])); ?>
                                        <br><small class="text-muted"><?php echo date('h:i A', strtotime($order['OrderDate'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $order['ItemCount']; ?> items</span>
                                        <?php if ($order['TotalItems']): ?>
                                            <br><small class="text-muted"><?php echo $order['TotalItems']; ?> total qty</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong>₱<?php echo number_format($order['Subtotal'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        switch ($order['OrderStatus']) {
                                            case 'Pending':
                                                $statusClass = 'bg-warning';
                                                break;
                                            case 'Processing':
                                                $statusClass = 'bg-info';
                                                break;
                                            case 'Completed':
                                                $statusClass = 'bg-success';
                                                break;
                                            case 'Cancelled':
                                                $statusClass = 'bg-danger';
                                                break;
                                            default:
                                                $statusClass = 'bg-secondary';
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($order['OrderStatus']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="order_details.php?id=<?php echo $order['OrderID']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($order['OrderStatus'] == 'Pending'): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="cancelOrder('<?php echo $order['OrderID']; ?>')">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Order Modal -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this order?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This action cannot be undone. Please contact support if you need assistance.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Order</button>
                <button type="button" class="btn btn-danger" id="confirmCancelBtn">Cancel Order</button>
            </div>
        </div>
    </div>
</div>

<script>
let orderToCancel = null;

function cancelOrder(orderId) {
    orderToCancel = orderId;
    const modal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
    modal.show();
}

document.getElementById('confirmCancelBtn').addEventListener('click', function() {
    if (orderToCancel) {
        // Here you would typically send an AJAX request to cancel the order
        // For now, we'll just show an alert
        alert('Order cancellation request submitted. Please contact support for assistance.');
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('cancelOrderModal'));
        modal.hide();
        
        orderToCancel = null;
    }
});
</script>

<?php include '../includes/footer.php'; ?>
