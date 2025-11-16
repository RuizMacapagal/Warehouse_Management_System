<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is cashier or admin
if ($_SESSION['role'] != 'cashier' && $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle order status updates
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = $_POST['order_id'];
    $newStatus = $_POST['new_status'];
    
    $stmt = $conn->prepare("UPDATE orders SET OrderStatus = ? WHERE OrderID = ?");
    $stmt->bind_param("ss", $newStatus, $orderId);
    
    if ($stmt->execute()) {
        $message = "Order status updated successfully!";
        $messageType = "success";
    } else {
        $message = "Error updating order status: " . $conn->error;
        $messageType = "danger";
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$dateFilter = $_GET['date'] ?? '';
$searchFilter = $_GET['search'] ?? '';

// Build query with filters
$whereConditions = [];
$params = [];
$types = '';

if ($statusFilter) {
    $whereConditions[] = "o.OrderStatus = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($dateFilter) {
    $whereConditions[] = "DATE(o.OrderDate) = ?";
    $params[] = $dateFilter;
    $types .= 's';
}

if ($searchFilter) {
    $whereConditions[] = "(c.CustomerName LIKE ? OR o.OrderID LIKE ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $types .= 'ss';
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Get orders with customer information
$query = "SELECT o.OrderID, o.OrderDate, o.Subtotal, o.OrderStatus, c.CustomerName as customer_name
          FROM orders o
          LEFT JOIN customer c ON o.CustomerID = c.CustomerID
          $whereClause
          ORDER BY o.OrderDate DESC
          LIMIT 100";

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
    SUM(CASE WHEN OrderStatus = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
    SUM(CASE WHEN OrderStatus = 'Paid' THEN 1 ELSE 0 END) as paid_orders,
    SUM(CASE WHEN OrderStatus = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
    SUM(CASE WHEN DATE(OrderDate) = CURDATE() THEN Subtotal ELSE 0 END) as today_sales
    FROM orders";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();
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
            <a class="nav-link active" href="orders.php">
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
                <h2 class="mb-0">Orders Management</h2>
                <a href="pos.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>New Order
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <i class="fas fa-info-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-shopping-cart fa-2x text-primary mb-2"></i>
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
                        <p class="text-muted mb-0">Pending Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($stats['paid_orders']); ?></h4>
                        <p class="text-muted mb-0">Paid Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-dollar-sign fa-2x text-info mb-2"></i>
                        <h4 class="mb-1">₱<?php echo number_format($stats['today_sales'], 2); ?></h4>
                        <p class="text-muted mb-0">Today's Sales</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card dashboard-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Paid" <?php echo $statusFilter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($dateFilter); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by customer or order ID..." value="<?php echo htmlspecialchars($searchFilter); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="card dashboard-card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Orders</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No orders found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['OrderID']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($order['OrderDate'])); ?></td>
                                    <td>₱<?php echo number_format($order['Subtotal'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $order['OrderStatus'] === 'Paid' ? 'success' : 
                                                ($order['OrderStatus'] === 'Pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($order['OrderStatus']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="viewOrder('<?php echo $order['OrderID']; ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success me-1" onclick="updateStatus('<?php echo $order['OrderID']; ?>', '<?php echo $order['OrderStatus']; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="receipt.php?order_id=<?php echo $order['OrderID']; ?>" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-receipt"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="status_order_id">
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select name="new_status" id="new_status" class="form-select" required>
                            <option value="Pending">Pending</option>
                            <option value="Paid">Paid</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// View order details
function viewOrder(orderId) {
    const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
    const content = document.getElementById('orderDetailsContent');
    
    // Show loading
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    // Remove # symbol if present in the order ID
    const cleanOrderId = orderId.replace('#', '');
    
    // Fetch order details via AJAX
    fetch(`../api/get_order_details.php?order_id=${cleanOrderId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                content.innerHTML = `
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Order ID:</strong> ${orderId}<br>
                            <strong>Customer:</strong> ${data.order.customer_name || 'N/A'}<br>
                            <strong>Date:</strong> ${new Date(data.order.order_date).toLocaleString()}
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong> <span class="badge bg-primary">${data.order.order_status}</span><br>
                            <strong>Total:</strong> ₱${parseFloat(data.order.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}
                        </div>
                    </div>
                    <h6>Order Items:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.items && data.items.length > 0 ? data.items.map(item => `
                                    <tr>
                                        <td>${item.product_name}</td>
                                        <td>₱${parseFloat(item.price).toFixed(2)}</td>
                                        <td>${item.quantity}</td>
                                        <td>₱${(parseFloat(item.price) * parseInt(item.quantity)).toFixed(2)}</td>
                                    </tr>
                                `).join('') : '<tr><td colspan="4" class="text-center">No items found</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                content.innerHTML = `<div class="alert alert-danger">Error loading order details: ${data.message}</div>`;
            }
        })
        .catch(error => {
            console.error('Error fetching order details:', error);
            content.innerHTML = `<div class="alert alert-danger">Error loading order details. Please try again.</div>`;
        });
}

// Update order status
function updateStatus(orderId, currentStatus) {
    document.getElementById('status_order_id').value = orderId;
    document.getElementById('new_status').value = currentStatus;
    
    const modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
    modal.show();
}
</script>

<?php include '../includes/footer.php'; ?>

