<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is customer
if ($_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit;
}

$customerID = $_SESSION['user_id'];
$orderID = $_GET['id'] ?? '';

if (!$orderID) {
    header("Location: orders.php");
    exit;
}

// Get order information
$orderQuery = "SELECT o.* FROM orders o WHERE o.OrderID = ? AND o.CustomerID = ?";
$stmt = $conn->prepare($orderQuery);
$stmt->bind_param("ss", $orderID, $customerID);
$stmt->execute();
$orderResult = $stmt->get_result();

if ($orderResult->num_rows === 0) {
    header("Location: orders.php");
    exit;
}

$order = $orderResult->fetch_assoc();

// Get order details
$detailsQuery = "SELECT od.*, p.ProductName 
                 FROM order_details od 
                 JOIN product p ON od.ProductID = p.ProductID 
                 WHERE od.OrderID = ?
                 ORDER BY od.OrderDetailID";
$stmt = $conn->prepare($detailsQuery);
$stmt->bind_param("s", $orderID);
$stmt->execute();
$detailsResult = $stmt->get_result();
$orderDetails = [];
while ($row = $detailsResult->fetch_assoc()) {
    $orderDetails[] = $row;
}

// Calculate totals
$subtotal = 0;
$totalItems = 0;
foreach ($orderDetails as $detail) {
    $subtotal += $detail['QuantityOrdered'] * $detail['ProductPrice'];
    $totalItems += $detail['QuantityOrdered'];
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
            <div class="col-md-12 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">Order Details</h2>
                    <p class="text-muted">Order #<?php echo htmlspecialchars($orderID); ?></p>
                </div>
                <a href="orders.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Orders
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Order Information -->
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Order Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-muted">Order ID</label>
                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($order['OrderID']); ?></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Order Date</label>
                            <p class="mb-0"><?php echo date('F d, Y', strtotime($order['OrderDate'])); ?></p>
                            <small class="text-muted"><?php echo date('h:i A', strtotime($order['OrderDate'])); ?></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Status</label>
                            <div>
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
                                <span class="badge <?php echo $statusClass; ?> fs-6">
                                    <?php echo htmlspecialchars($order['OrderStatus']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Total Items</label>
                            <p class="mb-0"><?php echo number_format($totalItems); ?> items</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Total Amount</label>
                            <p class="mb-0 fw-bold fs-5 text-primary">₱<?php echo number_format($order['Subtotal'], 2); ?></p>
                        </div>
                        
                        <?php if ($order['OrderStatus'] == 'Pending'): ?>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-danger" onclick="cancelOrder('<?php echo $order['OrderID']; ?>')">
                                <i class="fas fa-times me-2"></i>Cancel Order
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="col-md-8 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Order Items</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orderDetails)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No items found for this order.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orderDetails as $detail): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($detail['ProductName']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo number_format($detail['QuantityOrdered']); ?></span>
                                            </td>
                                            <td>₱<?php echo number_format($detail['ProductPrice'], 2); ?></td>
                                            <td>
                                                <strong>₱<?php echo number_format($detail['QuantityOrdered'] * $detail['ProductPrice'], 2); ?></strong>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-active">
                                            <th colspan="4" class="text-end">Total:</th>
                                            <th>₱<?php echo number_format($subtotal, 2); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Timeline -->
        <div class="row">
            <div class="col-md-12">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Order Timeline</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item <?php echo in_array($order['OrderStatus'], ['Pending', 'Processing', 'Completed']) ? 'completed' : ''; ?>">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Order Placed</h6>
                                    <p class="text-muted mb-0"><?php echo date('F d, Y h:i A', strtotime($order['OrderDate'])); ?></p>
                                </div>
                            </div>
                            
                            <?php if (in_array($order['OrderStatus'], ['Processing', 'Completed'])): ?>
                            <div class="timeline-item completed">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Order Confirmed</h6>
                                    <p class="text-muted mb-0">Your order has been confirmed and is being processed</p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($order['OrderStatus'] == 'Completed'): ?>
                            <div class="timeline-item completed">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Order Completed</h6>
                                    <p class="text-muted mb-0">Your order has been completed successfully</p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($order['OrderStatus'] == 'Cancelled'): ?>
                            <div class="timeline-item cancelled">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Order Cancelled</h6>
                                    <p class="text-muted mb-0">This order has been cancelled</p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
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

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -23px;
    top: 0;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #e9ecef;
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px #e9ecef;
}

.timeline-item.completed .timeline-marker {
    background: #28a745;
    box-shadow: 0 0 0 3px #28a745;
}

.timeline-item.cancelled .timeline-marker {
    background: #dc3545;
    box-shadow: 0 0 0 3px #dc3545;
}

.timeline-content h6 {
    color: #495057;
}

.timeline-item.completed .timeline-content h6 {
    color: #28a745;
}

.timeline-item.cancelled .timeline-content h6 {
    color: #dc3545;
}
</style>

<script>
function cancelOrder(orderId) {
    const modal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
    modal.show();
}

document.getElementById('confirmCancelBtn').addEventListener('click', function() {
    // Here you would typically send an AJAX request to cancel the order
    alert('Order cancellation request submitted. Please contact support for assistance.');
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('cancelOrderModal'));
    modal.hide();
});
</script>

<?php include '../includes/footer.php'; ?>
