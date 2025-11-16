<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is admin
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle order status updates
if (isset($_POST['update_status'])) {
    $orderID = $_POST['orderID'];
    $status = $_POST['status'];
    
    $query = "UPDATE orders SET OrderStatus = ? WHERE OrderID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $status, $orderID);
    
    if ($stmt->execute()) {
        $successMsg = "Order status updated successfully";
    } else {
        $errorMsg = "Error updating order status: " . $conn->error;
    }
}

// Get orders with customer info
$orders = [];
$query = "SELECT o.OrderID, o.OrderDate, o.Subtotal, o.OrderStatus, 
                 c.CustomerName, c.CustomerNumber AS ContactNumber
          FROM orders o 
          JOIN customer c ON o.CustomerID = c.CustomerID 
          ORDER BY o.OrderDate DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-warehouse"></i> 8JJ's Trading Incorporation</h3>
        <p>Admin Panel</p>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="products.php">
                <i class="fas fa-box"></i> Products
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="orders.php">
                <i class="fas fa-shopping-cart"></i> Orders
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="customers.php">
                <i class="fas fa-users"></i> Customers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="suppliers.php">
                <i class="fas fa-truck"></i> Suppliers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="users.php">
                <i class="fas fa-user-cog"></i> Users
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
                <h2 class="mb-0">Orders</h2>
                <div>
                    <button class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#filters">
                        <i class="fas fa-filter me-1"></i> Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-3" method="GET">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All</option>
                            <option value="Pending">Pending</option>
                            <option value="Paid">Paid</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to">
                    </div>
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="search" name="search" placeholder="Order ID or Customer...">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="ordersTable">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No orders found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['OrderID']; ?></td>
                                        <td>
                                            <?php echo $order['CustomerName']; ?><br>
                                            <small class="text-muted"><?php echo $order['ContactNumber']; ?></small>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($order['OrderDate'])); ?></td>
                                        <td>₱<?php echo number_format($order['Subtotal'], 2); ?></td>
                                        <td>
                                            <?php 
                                                $badgeClass = 'bg-secondary';
                                                if ($order['OrderStatus'] == 'Pending') $badgeClass = 'bg-warning';
                                                elseif ($order['OrderStatus'] == 'Paid') $badgeClass = 'bg-success';
                                                elseif ($order['OrderStatus'] == 'Cancelled') $badgeClass = 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo $order['OrderStatus']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateStatus<?php echo $order['OrderID']; ?>">
                                                <i class="fas fa-edit"></i> Update
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Update Status Modal -->
                                    <div class="modal fade" id="updateStatus<?php echo $order['OrderID']; ?>" tabindex="-1" aria-labelledby="updateStatusLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Update Order Status</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="" method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="orderID" value="<?php echo $order['OrderID']; ?>">
                                                        <div class="mb-3">
                                                            <label for="status<?php echo $order['OrderID']; ?>" class="form-label">Status</label>
                                                            <select class="form-select" id="status<?php echo $order['OrderID']; ?>" name="status" required>
                                                                <option value="Pending" <?php echo ($order['OrderStatus'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="Paid" <?php echo ($order['OrderStatus'] == 'Paid') ? 'selected' : ''; ?>>Paid</option>
                                                                <option value="Cancelled" <?php echo ($order['OrderStatus'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary" name="update_status">Save changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('ordersTable');
        if (table) {
            $(table).DataTable();
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
