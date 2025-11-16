<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is cashier or admin
if ($_SESSION['role'] != 'cashier' && $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle customer operations
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_customer'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        if ($username && $password) {
            // Check if customer already exists by name or phone
            $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM customer WHERE CustomerName = ? OR CustomerNumber = ?");
            $checkStmt->bind_param("ss", $username, $phone);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $message = "Customer already exists!";
                $messageType = "warning";
            } else {
                // 1) Create user account with role 'customer'
                $userStmt = $conn->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, 'customer', ?)");
                $userStmt->bind_param("sss", $username, $password, $email);
                if ($userStmt->execute()) {
                    $newUserId = (string)$conn->insert_id; // Map to CustomerID
                    
                    // 2) Create customer record using same ID for consistency across dashboards
                    $stmt = $conn->prepare("INSERT INTO customer (CustomerID, CustomerName, CustomerNumber, CustomerAddress, CustomerEmail) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $newUserId, $username, $phone, $address, $email);
                    
                    if ($stmt->execute()) {
                        $message = "Customer added successfully with account!";
                        $messageType = "success";
                    } else {
                        $message = "Error adding customer: " . $conn->error;
                        $messageType = "danger";
                    }
                } else {
                    $message = "Error creating user account: " . $conn->error;
                    $messageType = "danger";
                }
            }
        } else {
            $message = "Username and password are required.";
            $messageType = "warning";
        }
    }
}

// Get search parameter
$searchFilter = $_GET['search'] ?? '';

// Build query with search filter (use customer fields, no users join)
$whereClause = "WHERE 1=1";
$params = [];
$types = '';

if ($searchFilter) {
    $whereClause .= " AND (c.CustomerName LIKE ? OR c.CustomerEmail LIKE ? OR c.CustomerNumber LIKE ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $types = 'sss';
}

// Get customers with order statistics
$query = "SELECT c.CustomerID, c.CustomerName, c.CustomerNumber, c.CustomerAddress,
          COUNT(o.OrderID) as total_orders,
          COALESCE(SUM(o.Subtotal), 0) as total_spent,
          MAX(o.OrderDate) as last_order_date
          FROM customer c
          LEFT JOIN orders o ON c.CustomerID = o.CustomerID
          $whereClause
          GROUP BY c.CustomerID, c.CustomerName, c.CustomerNumber, c.CustomerAddress
          ORDER BY c.CustomerName";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}

// Get customer statistics (basic)
$statsQuery = "SELECT COUNT(*) as total_customers FROM customer";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Add default values for missing statistics (customer table has no timestamp fields)
$stats['new_today'] = 0;
$stats['new_this_week'] = 0;
$stats['new_this_month'] = 0;
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
            <a class="nav-link active" href="customers.php">
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
                <h2 class="mb-0">Customer Management</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                    <i class="fas fa-plus me-2"></i>Add Customer
                </button>
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
                        <i class="fas fa-users fa-2x text-primary mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($stats['total_customers']); ?></h4>
                        <p class="text-muted mb-0">Total Customers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-user-plus fa-2x text-success mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($stats['new_today']); ?></h4>
                        <p class="text-muted mb-0">New Today</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-calendar-week fa-2x text-info mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($stats['new_this_week']); ?></h4>
                        <p class="text-muted mb-0">New This Week</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-calendar-alt fa-2x text-warning mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($stats['new_this_month']); ?></h4>
                        <p class="text-muted mb-0">New This Month</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search -->
        <div class="card dashboard-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-10">
                        <input type="text" name="search" class="form-control" placeholder="Search customers by name, email, or phone..." value="<?php echo htmlspecialchars($searchFilter); ?>">
                    </div>
                    <div class="col-md-2">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Search</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Customers Table -->
        <div class="card dashboard-card">
            <div class="card-header bg-white">
                <h5 class="mb-0">All Customers</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Contact Info</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Last Order</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No customers found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($customer['CustomerName'] ?? ''); ?></strong>
                                            <?php if (isset($customer['CustomerAddress']) && $customer['CustomerAddress']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($customer['CustomerAddress']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (isset($customer['CustomerNumber']) && $customer['CustomerNumber']): ?>
                                            <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($customer['CustomerNumber']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $customer['total_orders']; ?></span>
                                    </td>
                                    <td>₱<?php echo number_format($customer['total_spent'], 2); ?></td>
                                    <td>
                                        <?php if ($customer['last_order_date']): ?>
                                            <?php echo date('M d, Y', strtotime($customer['last_order_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">No orders</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo isset($customer['created_at']) && $customer['created_at'] ? date('M d, Y', strtotime($customer['created_at'])) : 'N/A'; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editCustomer('<?php echo htmlspecialchars($customer['CustomerID']); ?>', '<?php echo htmlspecialchars($customer['CustomerName'] ?? ''); ?>', '<?php echo htmlspecialchars($customer['CustomerNumber'] ?? ''); ?>', '<?php echo htmlspecialchars($customer['CustomerAddress'] ?? ''); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" onclick="viewCustomerOrders('<?php echo htmlspecialchars($customer['CustomerID']); ?>', '<?php echo htmlspecialchars($customer['CustomerName'] ?? ''); ?>')">
                                            <i class="fas fa-shopping-cart"></i>
                                        </button>
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

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_customer" class="btn btn-primary">Add Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="customer_id" id="edit_customer_id">
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" id="edit_username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="edit_email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" id="edit_phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" id="edit_address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_customer" class="btn btn-primary">Update Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Customer Orders Modal -->
<div class="modal fade" id="customerOrdersModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerOrdersTitle">Customer Orders</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="customerOrdersContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Edit customer function
function editCustomer(id, name, number, address) {
    document.getElementById('edit_customer_id').value = id;
    document.getElementById('edit_username').value = name || '';
    document.getElementById('edit_email').value = '';
    document.getElementById('edit_phone').value = number || '';
    document.getElementById('edit_address').value = address || '';
    
    const modal = new bootstrap.Modal(document.getElementById('editCustomerModal'));
    modal.show();
}

// View customer orders
function viewCustomerOrders(customerId, customerName) {
    document.getElementById('customerOrdersTitle').textContent = `Orders for ${customerName}`;
    const content = document.getElementById('customerOrdersContent');
    
    // Show loading
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('customerOrdersModal'));
    modal.show();
    
    // Fetch customer orders via AJAX
    fetch(`../api/get_customer_orders.php?customer_id=${customerId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.orders.length === 0) {
                    content.innerHTML = '<div class="text-center text-muted">No orders found for this customer.</div>';
                } else {
                    content.innerHTML = `
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.orders.map(order => `
                                        <tr>
                                            <td>#${order.OrderID}</td>
                                            <td>${new Date(order.OrderDate).toLocaleDateString()}</td>
                                            <td>₱${parseFloat(order.Subtotal).toFixed(2)}</td>
                                            <td><span class="badge bg-primary">${order.OrderStatus}</span></td>
                                            <td>
                                                <a href="receipt.php?order_id=${order.OrderID}" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-receipt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                }
            } else {
                content.innerHTML = `<div class="alert alert-danger">Error loading orders: ${data.message}</div>`;
            }
        })
        .catch(error => {
            content.innerHTML = `<div class="alert alert-danger">Error loading orders</div>`;
        });
}
</script>

<?php include '../includes/footer.php'; ?>
