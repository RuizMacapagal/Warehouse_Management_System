<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is admin
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle customer operations
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_customer'])) {
        // Collect both profile and account credentials
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $email = trim($_POST['email']);
        $name = trim($_POST['customer_name']);
        $phone = trim($_POST['customer_phone']);
        $address = trim($_POST['customer_address']);
        
        if ($username && $password && $name && $phone && $address) {
            // Check if username already exists
            $checkUser = $conn->prepare("SELECT COUNT(*) AS cnt FROM users WHERE username = ?");
            $checkUser->bind_param("s", $username);
            $checkUser->execute();
            $res = $checkUser->get_result();
            $row = $res ? $res->fetch_assoc() : ['cnt' => 0];
            if ((int)($row['cnt'] ?? 0) > 0) {
                $message = "Username already exists. Please choose another.";
                $messageType = "warning";
            } else {
                // 1) Create user account with role 'customer'
                $userStmt = $conn->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, 'customer', ?)");
                $userStmt->bind_param("sss", $username, $password, $email);
                if ($userStmt->execute()) {
                    $newUserId = (string)$conn->insert_id; // map to CustomerID

                    // 2) Create customer record using same ID
                    $stmt = $conn->prepare("INSERT INTO customer (CustomerID, CustomerName, CustomerNumber, CustomerAddress, CustomerEmail) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $newUserId, $name, $phone, $address, $email);
                    
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
            $message = "Please fill in all required fields.";
            $messageType = "warning";
        }
    }
    
    if (isset($_POST['update_customer'])) {
        $customerID = $_POST['customer_id'];
        $name = trim($_POST['customer_name']);
        $phone = trim($_POST['customer_phone']);
        $address = trim($_POST['customer_address']);
        
        if ($name && $phone && $address) {
            $stmt = $conn->prepare("UPDATE customer SET CustomerName = ?, CustomerNumber = ?, CustomerAddress = ? WHERE CustomerID = ?");
            $stmt->bind_param("ssss", $name, $phone, $address, $customerID);
            
            if ($stmt->execute()) {
                $message = "Customer updated successfully!";
                $messageType = "success";
            } else {
                $message = "Error updating customer: " . $conn->error;
                $messageType = "danger";
            }
        } else {
            $message = "Please fill in all fields.";
            $messageType = "warning";
        }
    }
    
    if (isset($_POST['delete_customer'])) {
        $customerID = $_POST['customer_id'];
        
        // Check if customer has orders
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE CustomerID = ?");
        $checkStmt->bind_param("s", $customerID);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $message = "Cannot delete customer with existing orders.";
            $messageType = "warning";
        } else {
            $stmt = $conn->prepare("DELETE FROM customer WHERE CustomerID = ?");
            $stmt->bind_param("s", $customerID);
            
            if ($stmt->execute()) {
                $message = "Customer deleted successfully!";
                $messageType = "success";
            } else {
                $message = "Error deleting customer: " . $conn->error;
                $messageType = "danger";
            }
        }
    }
}

// Get all customers
$customers = [];
$query = "SELECT c.*, COUNT(o.OrderID) as order_count 
          FROM customer c 
          LEFT JOIN orders o ON c.CustomerID = o.CustomerID 
          GROUP BY c.CustomerID 
          ORDER BY c.CustomerName";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
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
            <a class="nav-link" href="orders.php">
                <i class="fas fa-shopping-cart"></i> Orders
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="customers.php">
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

        <div class="card dashboard-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">All Customers</h5>
                <input type="text" class="form-control" id="customerSearch" placeholder="Search customers..." style="max-width: 250px;">
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="customersTable">
                        <thead>
                            <tr>
                                <th>Customer ID</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Total Orders</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $i => $customer): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo htmlspecialchars($customer['CustomerName']); ?></td>
                                <td><?php echo htmlspecialchars($customer['CustomerNumber']); ?></td>
                                <td><?php echo htmlspecialchars($customer['CustomerAddress']); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $customer['order_count']; ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editCustomer('<?php echo $customer['CustomerID']; ?>', '<?php echo htmlspecialchars($customer['CustomerName']); ?>', '<?php echo htmlspecialchars($customer['CustomerNumber']); ?>', '<?php echo htmlspecialchars($customer['CustomerAddress']); ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($customer['order_count'] == 0): ?>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteCustomer('<?php echo $customer['CustomerID']; ?>', '<?php echo htmlspecialchars($customer['CustomerName']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" disabled title="Cannot delete customer with orders">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
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
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer Name</label>
                        <input type="text" class="form-control" name="customer_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="customer_phone" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="customer_address" rows="3" required></textarea>
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
                        <label class="form-label">Customer Name</label>
                        <input type="text" class="form-control" name="customer_name" id="edit_customer_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="customer_phone" id="edit_customer_phone" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="customer_address" id="edit_customer_address" rows="3" required></textarea>
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

<!-- Delete Customer Modal -->
<div class="modal fade" id="deleteCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="customer_id" id="delete_customer_id">
                    <p>Are you sure you want to delete customer <strong id="delete_customer_name"></strong>?</p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_customer" class="btn btn-danger">Delete Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('customerSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#customersTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Edit customer function
function editCustomer(id, name, phone, address) {
    document.getElementById('edit_customer_id').value = id;
    document.getElementById('edit_customer_name').value = name;
    document.getElementById('edit_customer_phone').value = phone;
    document.getElementById('edit_customer_address').value = address;
    
    const modal = new bootstrap.Modal(document.getElementById('editCustomerModal'));
    modal.show();
}

// Delete customer function
function deleteCustomer(id, name) {
    document.getElementById('delete_customer_id').value = id;
    document.getElementById('delete_customer_name').textContent = name;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteCustomerModal'));
    modal.show();
}
</script>

<?php include '../includes/footer.php'; ?>