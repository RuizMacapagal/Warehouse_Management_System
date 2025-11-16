<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is admin
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle user operations
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $role = $_POST['role'];
        $email = trim($_POST['email']);
        
        if ($username && $password && $role) {
            // Check if username already exists
            $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
            $checkStmt->bind_param("s", $username);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $message = "Username already exists!";
                $messageType = "warning";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $username, $hashedPassword, $role, $email);
                
                if ($stmt->execute()) {
                    $message = "User added successfully!";
                    $messageType = "success";
                } else {
                    $message = "Error adding user: " . $conn->error;
                    $messageType = "danger";
                }
            }
        } else {
            $message = "Please fill in required fields.";
            $messageType = "warning";
        }
    }
    
    if (isset($_POST['update_user'])) {
        $userId = $_POST['user_id'];
        $username = trim($_POST['username']);
        $role = $_POST['role'];
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        
        if ($username && $role) {
            if ($password) {
                // Update with new password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $username, $hashedPassword, $role, $email, $userId);
            } else {
                // Update without changing password
                $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, email = ? WHERE id = ?");
                $stmt->bind_param("sssi", $username, $role, $email, $userId);
            }
            
            if ($stmt->execute()) {
                $message = "User updated successfully!";
                $messageType = "success";
            } else {
                $message = "Error updating user: " . $conn->error;
                $messageType = "danger";
            }
        } else {
            $message = "Please fill in required fields.";
            $messageType = "warning";
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $userId = $_POST['user_id'];
        
        // Prevent deleting current user
        if ($userId == $_SESSION['user_id']) {
            $message = "Cannot delete your own account!";
            $messageType = "warning";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            
            if ($stmt->execute()) {
                $message = "User deleted successfully!";
                $messageType = "success";
            } else {
                $message = "Error deleting user: " . $conn->error;
                $messageType = "danger";
            }
        }
    }
}

// Get all users
$users = [];
$query = "SELECT * FROM users ORDER BY id ASC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
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
            <a class="nav-link active" href="users.php">
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
                <h2 class="mb-0">User Management</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus me-2"></i>Add User
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
                <h5 class="mb-0">All Users</h5>
                <input type="text" class="form-control" id="userSearch" placeholder="Search users..." style="max-width: 250px;">
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <span class="badge bg-primary ms-1">You</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $user['role'] === 'admin' ? 'danger' : 
                                            ($user['role'] === 'cashier' ? 'success' : 
                                            ($user['role'] === 'inventory' ? 'warning' : 'info')); 
                                    ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo $user['role']; ?>', '<?php echo htmlspecialchars($user['email'] ?? ''); ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" disabled title="Cannot delete your own account">
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
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
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-control" name="role" required>
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="cashier">Cashier</option>
                            <option value="inventory">Inventory</option>
                            <option value="customer">Customer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" id="edit_username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
                        <input type="password" class="form-control" name="password" id="edit_password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-control" name="role" id="edit_role" required>
                            <option value="admin">Admin</option>
                            <option value="cashier">Cashier</option>
                            <option value="inventory">Inventory</option>
                            <option value="customer">Customer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="edit_email">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <p>Are you sure you want to delete user <strong id="delete_username"></strong>?</p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('userSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#usersTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Edit user function
function editUser(id, username, role, email) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_password').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

// Delete user function
function deleteUser(id, username) {
    document.getElementById('delete_user_id').value = id;
    document.getElementById('delete_username').textContent = username;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
    modal.show();
}
</script>

<?php include '../includes/footer.php'; ?>
