<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is inventory or admin
if ($_SESSION['role'] != 'inventory' && $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle supplier operations
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_supplier'])) {
        $name = trim($_POST['name']);
        $contactPerson = trim($_POST['contact_person']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        if ($name) {
            $supplierId = 'S-' . date('YmdHis') . '-' . rand(100, 999);
            $stmt = $conn->prepare("INSERT INTO supplier (SupplierID, CompanyName, ContactNumber) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $supplierId, $name, $phone);
            
            if ($stmt->execute()) {
                $message = "Supplier added successfully!";
                $messageType = "success";
            } else {
                $message = "Error adding supplier: " . $conn->error;
                $messageType = "danger";
            }
        } else {
            $message = "Please provide supplier name.";
            $messageType = "warning";
        }
    }
    
    if (isset($_POST['update_supplier'])) {
        $supplierId = $_POST['supplier_id'];
        $name = trim($_POST['name']);
        $contactPerson = trim($_POST['contact_person']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        if ($name) {
            $stmt = $conn->prepare("UPDATE supplier SET CompanyName = ?, ContactNumber = ? WHERE SupplierID = ?");
            $stmt->bind_param("sss", $name, $phone, $supplierId);
            
            if ($stmt->execute()) {
                $message = "Supplier updated successfully!";
                $messageType = "success";
            } else {
                $message = "Error updating supplier: " . $conn->error;
                $messageType = "danger";
            }
        } else {
            $message = "Please provide supplier name.";
            $messageType = "warning";
        }
    }
    
    if (isset($_POST['delete_supplier'])) {
        $supplierId = $_POST['supplier_id'];
        
        $stmt = $conn->prepare("DELETE FROM supplier WHERE SupplierID = ?");
        $stmt->bind_param("s", $supplierId);
        
        if ($stmt->execute()) {
            $message = "Supplier deleted successfully!";
            $messageType = "success";
        } else {
            $message = "Error deleting supplier: " . $conn->error;
            $messageType = "danger";
        }
    }
}

// Get search parameter
$searchFilter = $_GET['search'] ?? '';

// Build query with search filter
$whereClause = "WHERE 1=1";
$params = [];
$types = '';

if ($searchFilter) {
    $whereClause .= " AND (s.CompanyName LIKE ? OR s.ContactNumber LIKE ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $types = 'ss';
}

// Get suppliers (legacy schema)
$query = "SELECT s.SupplierID, s.CompanyName, s.ContactNumber FROM supplier s $whereClause ORDER BY s.CompanyName";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$suppliers = [];
while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}

// Get supplier statistics
$statsQuery = "SELECT COUNT(*) as total_suppliers FROM supplier";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult ? $statsResult->fetch_assoc() : ['total_suppliers' => 0];
$stats['new_this_month'] = $stats['new_this_month'] ?? 0;
$stats['active_this_month'] = $stats['active_this_month'] ?? 0;
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-warehouse"></i> 8JJ's Trading Incorporation</h3>
        <p>Inventory Management</p>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="stock.php">
                <i class="fas fa-boxes"></i> Stock Management
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="receive.php">
                <i class="fas fa-truck"></i> Receive Stock
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="suppliers.php">
                <i class="fas fa-building"></i> Suppliers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar"></i> Reports
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

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-building fa-2x text-primary mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($stats['total_suppliers']); ?></h4>
                        <p class="text-muted mb-0">Total Suppliers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-plus-circle fa-2x text-success mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($stats['new_this_month']); ?></h4>
                        <p class="text-muted mb-0">New This Month</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-truck fa-2x text-info mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($stats['active_this_month']); ?></h4>
                        <p class="text-muted mb-0">Active This Month</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search -->
        <div class="card dashboard-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-10">
                        <input type="text" name="search" class="form-control" placeholder="Search suppliers by name, contact person, email, or phone..." value="<?php echo htmlspecialchars($searchFilter); ?>">
                    </div>
                    <div class="col-md-2">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Search</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Suppliers Table -->
        <div class="card dashboard-card">
            <div class="card-header bg-white">
                <h5 class="mb-0">All Suppliers</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Supplier</th>
                                <th>Contact Info</th>
                                <th>Statistics</th>
                                <th>Last Delivery</th>
                                <th>Total Purchases</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($suppliers)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No suppliers found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($suppliers as $supplier): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($supplier['CompanyName']); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($supplier['ContactNumber']): ?>
                                            <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($supplier['ContactNumber']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">0 deliveries</span>
                                    </td>
                                    <td>
                                        <span class="text-muted">No deliveries</span>
                                    </td>
                                    <td>₱<?php echo number_format(0, 2); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editSupplier('<?php echo $supplier['SupplierID']; ?>', '<?php echo htmlspecialchars($supplier['CompanyName']); ?>', '', '', '<?php echo htmlspecialchars($supplier['ContactNumber'] ?? ''); ?>', '')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteSupplier('<?php echo $supplier['SupplierID']; ?>', '<?php echo htmlspecialchars($supplier['CompanyName']); ?>')">
                                            <i class="fas fa-trash"></i>
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
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" class="form-control" name="contact_person">
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
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" class="form-control" name="contact_person" id="edit_contact_person">
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
                    <p>Are you sure you want to delete the supplier "<span id="delete_supplier_name"></span>"?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone. The supplier can only be deleted if there are no existing stock receipts.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_supplier" class="btn btn-danger">Delete Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Supplier History Modal -->
<div class="modal fade" id="supplierHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="supplierHistoryTitle">Supplier History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="supplierHistoryContent">
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
// Edit supplier function
function editSupplier(id, name, contactPerson, email, phone, address) {
    document.getElementById('edit_supplier_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_contact_person').value = contactPerson;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_phone').value = phone;
    document.getElementById('edit_address').value = address;
    
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

// View supplier history
function viewSupplierHistory(supplierId, supplierName) {
    document.getElementById('supplierHistoryTitle').textContent = `Delivery History - ${supplierName}`;
    const content = document.getElementById('supplierHistoryContent');
    
    // Show loading
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('supplierHistoryModal'));
    modal.show();
    
    // Fetch supplier history via AJAX
    fetch(`../api/get_supplier_history.php?supplier_id=${supplierId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.receipts.length === 0) {
                    content.innerHTML = '<div class="text-center text-muted">No delivery history found for this supplier.</div>';
                } else {
                    content.innerHTML = `
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Unit Cost</th>
                                        <th>Total</th>
                                        <th>Received By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.receipts.map(receipt => `
                                        <tr>
                                            <td>${new Date(receipt.received_date).toLocaleDateString()}</td>
                                            <td>${receipt.product_name}</td>
                                            <td>${receipt.quantity_received}</td>
                                            <td>₱${parseFloat(receipt.unit_cost).toFixed(2)}</td>
                                            <td>₱${parseFloat(receipt.total_cost).toFixed(2)}</td>
                                            <td>${receipt.received_by_name}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                }
            } else {
                content.innerHTML = `<div class="alert alert-danger">Error loading history: ${data.message}</div>`;
            }
        })
        .catch(error => {
            content.innerHTML = `<div class="alert alert-danger">Error loading history</div>`;
        });
}
</script>

<?php include '../includes/footer.php'; ?>
