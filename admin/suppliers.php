<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is admin
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle supplier operations
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_supplier'])) {
        $name = trim($_POST['supplier_name']);
        $contact = trim($_POST['supplier_contact']);
        $address = trim($_POST['supplier_address']);
        $email = trim($_POST['supplier_email']);
        
        if ($name && $contact) {
            $supplierId = 'S-' . date('YmdHis') . '-' . rand(100, 999);
            $stmt = $conn->prepare("INSERT INTO supplier (SupplierID, CompanyName, ContactNumber, Email, Address) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $supplierId, $name, $contact, $email, $address);
            
            if ($stmt->execute()) {
                $message = "Supplier added successfully!";
                $messageType = "success";
            } else {
                $message = "Error adding supplier: " . $conn->error;
                $messageType = "danger";
            }
        } else {
            $message = "Please fill in required fields.";
            $messageType = "warning";
        }
    }
    
    if (isset($_POST['update_supplier'])) {
        $supplierID = $_POST['supplier_id'];
        $name = trim($_POST['supplier_name']);
        $contact = trim($_POST['supplier_contact']);
        $address = trim($_POST['supplier_address']);
        $email = trim($_POST['supplier_email']);
        
        if ($name && $contact) {
            $stmt = $conn->prepare("UPDATE supplier SET CompanyName = ?, ContactNumber = ?, Email = ?, Address = ? WHERE SupplierID = ?");
            $stmt->bind_param("sssss", $name, $contact, $email, $address, $supplierID);
            
            if ($stmt->execute()) {
                $message = "Supplier updated successfully!";
                $messageType = "success";
            } else {
                $message = "Error updating supplier: " . $conn->error;
                $messageType = "danger";
            }
        } else {
            $message = "Please fill in required fields.";
            $messageType = "warning";
        }
    }
    
    if (isset($_POST['delete_supplier'])) {
        $supplierID = $_POST['supplier_id'];
        
        $stmt = $conn->prepare("DELETE FROM supplier WHERE SupplierID = ?");
        $stmt->bind_param("s", $supplierID);
        
        if ($stmt->execute()) {
            $message = "Supplier deleted successfully!";
            $messageType = "success";
        } else {
            $message = "Error deleting supplier: " . $conn->error;
            $messageType = "danger";
        }
    }
}

// Get all suppliers
$suppliers = [];
$query = "SELECT SupplierID, CompanyName, ContactNumber, Email, Address FROM supplier ORDER BY CompanyName";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
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
            <a class="nav-link active" href="suppliers.php">
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
                <h2 class="mb-0">Supplier Management</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                    <i class="fas fa-plus me-2"></i>Add Supplier
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
                <h5 class="mb-0">All Suppliers</h5>
                <input type="text" class="form-control" id="supplierSearch" placeholder="Search suppliers..." style="max-width: 250px;">
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="suppliersTable">
                        <thead>
                            <tr>
                                <th>Supplier ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Email</th>
                                <th>Address</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $supplier): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($supplier['SupplierID']); ?></td>
                                <td><?php echo htmlspecialchars($supplier['CompanyName']); ?></td>
                                <td><?php echo htmlspecialchars($supplier['ContactNumber']); ?></td>
                                <td><?php echo htmlspecialchars($supplier['Email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($supplier['Address'] ?? 'N/A'); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editSupplier('<?php echo $supplier['SupplierID']; ?>', '<?php echo htmlspecialchars($supplier['CompanyName']); ?>', '<?php echo htmlspecialchars($supplier['ContactNumber']); ?>', '<?php echo htmlspecialchars($supplier['Address'] ?? ''); ?>', '<?php echo htmlspecialchars($supplier['Email'] ?? ''); ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteSupplier('<?php echo $supplier['SupplierID']; ?>', '<?php echo htmlspecialchars($supplier['CompanyName']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
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

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="supplier_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="supplier_contact" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="supplier_email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="supplier_address" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_supplier" class="btn btn-primary">Add Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div class="modal fade" id="editSupplierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="supplier_id" id="edit_supplier_id">
                    <div class="mb-3">
                        <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="supplier_name" id="edit_supplier_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="supplier_contact" id="edit_supplier_contact" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="supplier_email" id="edit_supplier_email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="supplier_address" id="edit_supplier_address" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_supplier" class="btn btn-primary">Update Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Supplier Modal -->
<div class="modal fade" id="deleteSupplierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="supplier_id" id="delete_supplier_id">
                    <p>Are you sure you want to delete supplier <strong id="delete_supplier_name"></strong>?</p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_supplier" class="btn btn-danger">Delete Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('supplierSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#suppliersTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Edit supplier function
function editSupplier(id, name, contact, address, email) {
    document.getElementById('edit_supplier_id').value = id;
    document.getElementById('edit_supplier_name').value = name;
    document.getElementById('edit_supplier_contact').value = contact;
    document.getElementById('edit_supplier_address').value = address;
    document.getElementById('edit_supplier_email').value = email;
    
    const modal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
    modal.show();
}

// Delete supplier function
function deleteSupplier(id, name) {
    document.getElementById('delete_supplier_id').value = id;
    document.getElementById('delete_supplier_name').textContent = name;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteSupplierModal'));
    modal.show();
}
</script>

<?php include '../includes/footer.php'; ?>
