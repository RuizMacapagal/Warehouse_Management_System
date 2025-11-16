<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is inventory or admin
if ($_SESSION['role'] != 'inventory' && $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle stock receiving
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['receive_stock'])) {
        $productId = $_POST['product_id'];
        $quantity = $_POST['quantity'];
        $supplierId = $_POST['supplier_id'];
        $unitCost = $_POST['unit_cost'];
        $notes = trim($_POST['notes']);
        
        if ($productId && $quantity > 0 && $supplierId) {
            $conn->begin_transaction();
            
            try {
                // Update product stock
$updateStmt = $conn->prepare("UPDATE product SET StockOnHandPerCase = StockOnHandPerCase + ? WHERE ProductID = ?");
$updateStmt->bind_param("is", $quantity, $productId);
                $updateStmt->execute();
                
                // Log stock receipt in legacy-compatible table
                $receiptID = 'SR-' . date('YmdHis') . '-' . rand(100, 999);
                $receivedBy = $_SESSION['user_id'];
                $receiptStmt = $conn->prepare("INSERT INTO stock_receipts (ReceiptID, SupplierID, ProductID, Quantity, UnitCost, ReceivedBy, Notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $receiptStmt->bind_param("sssidss", $receiptID, $supplierId, $productId, $quantity, $unitCost, $receivedBy, $notes);
                $receiptStmt->execute();
                
                $conn->commit();
                
                $message = "Stock received successfully! Added $quantity cases.";
                $messageType = "success";
                
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error receiving stock: " . $e->getMessage();
                $messageType = "danger";
            }
        } else {
            $message = "Please fill in all required fields.";
            $messageType = "warning";
        }
    }
}

// Get products for dropdown
$productsQuery = "SELECT ProductID, ProductName, StockOnHandPerCase FROM product ORDER BY ProductName";
$productsResult = $conn->query($productsQuery);
$products = [];
while ($row = $productsResult->fetch_assoc()) {
    $products[] = $row;
}

// Get suppliers for dropdown (legacy schema)
$suppliersQuery = "SELECT SupplierID, CompanyName, ContactNumber FROM supplier ORDER BY CompanyName";
$suppliersResult = $conn->query($suppliersQuery);
$suppliers = [];
while ($row = $suppliersResult->fetch_assoc()) {
    $suppliers[] = $row;
}

// Recent receipts disabled: use empty list for legacy schema
$recentReceipts = [];

// Today's statistics disabled: provide zero defaults
$todayStats = [
    'receipts_today' => 0,
    'total_quantity_today' => 0,
    'total_value_today' => 0
];
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
            <a class="nav-link active" href="receive.php">
                <i class="fas fa-truck"></i> Receive Stock
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="suppliers.php">
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
            <div class="col-md-12">
                <h2 class="mb-0">Receive Stock</h2>
                <p class="text-muted">Record incoming stock deliveries</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <i class="fas fa-info-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Today's Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-truck fa-2x text-primary mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($todayStats['receipts_today']); ?></h4>
                        <p class="text-muted mb-0">Receipts Today</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-boxes fa-2x text-success mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($todayStats['total_quantity_today']); ?></h4>
                        <p class="text-muted mb-0">Units Received Today</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-peso-sign fa-2x text-info mb-2"></i>
                        <h4 class="mb-1">₱<?php echo number_format($todayStats['total_value_today'], 2); ?></h4>
                        <p class="text-muted mb-0">Value Received Today</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Receive Stock Form -->
            <div class="col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Receive New Stock</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Product <span class="text-danger">*</span></label>
                                <select class="form-select" name="product_id" id="product_select" required onchange="updateCurrentStock()">
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['ProductID']; ?>" data-stock="<?php echo $product['StockOnHandPerCase']; ?>">
                                            <?php echo htmlspecialchars($product['ProductName']); ?> (ID: <?php echo htmlspecialchars($product['ProductID']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Current Stock</label>
                                <input type="text" class="form-control" id="current_stock_display" readonly placeholder="Select a product">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Supplier <span class="text-danger">*</span></label>
                                <select class="form-select" name="supplier_id" required>
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
    <option value="<?php echo $supplier['SupplierID']; ?>">
        <?php echo htmlspecialchars($supplier['CompanyName']); ?>
        <?php if (!empty($supplier['ContactNumber'])): ?>
            (<?php echo htmlspecialchars($supplier['ContactNumber']); ?>)
        <?php endif; ?>
    </option>
<?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Quantity Received <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="quantity" min="1" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Unit Cost <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="unit_cost" step="0.01" min="0" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="Optional notes about this delivery"></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="receive_stock" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Receive Stock
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <a href="stock.php?stock_filter=low" class="btn btn-warning w-100 py-3">
                                    <i class="fas fa-exclamation-triangle fa-2x d-block mb-2"></i>
                                    <strong>View Low Stock Items</strong>
                                    <br><small>Check products that need restocking</small>
                                </a>
                            </div>
                            <div class="col-12">
                                <a href="suppliers.php" class="btn btn-info w-100 py-3">
                                    <i class="fas fa-building fa-2x d-block mb-2"></i>
                                    <strong>Manage Suppliers</strong>
                                    <br><small>Add or edit supplier information</small>
                                </a>
                            </div>
                            <div class="col-12">
                                <a href="reports.php" class="btn btn-success w-100 py-3">
                                    <i class="fas fa-chart-line fa-2x d-block mb-2"></i>
                                    <strong>Stock Reports</strong>
                                    <br><small>View receiving and stock reports</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Receipts -->
        <div class="card dashboard-card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Stock Receipts</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Supplier</th>
                                <th>Quantity</th>
                                <th>Unit Cost</th>
                                <th>Total Cost</th>
                                <th>Received By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentReceipts)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No recent receipts</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentReceipts as $receipt): ?>
                                <tr>
                                    <td><?php echo date('M d, Y g:i A', strtotime($receipt['received_date'])); ?></td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($receipt['product_name']); ?></strong>
                                            <br><small class="text-muted">ID: <?php echo htmlspecialchars($receipt['product_id']); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($receipt['supplier_name']); ?></td>
                                    <td>
                                        <span class="badge bg-success"><?php echo number_format($receipt['quantity_received']); ?></span>
                                    </td>
                                    <td>₱<?php echo number_format($receipt['unit_cost'], 2); ?></td>
                                    <td>₱<?php echo number_format($receipt['total_cost'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($receipt['received_by_name']); ?></td>
                                    <td>
                                        <?php if ($receipt['notes']): ?>
                                            <small><?php echo htmlspecialchars($receipt['notes']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
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

<script>
function updateCurrentStock() {
    const select = document.getElementById('product_select');
    const display = document.getElementById('current_stock_display');
    
    if (select.value) {
        const selectedOption = select.options[select.selectedIndex];
        const currentStock = selectedOption.getAttribute('data-stock');
        display.value = currentStock + ' units';
    } else {
        display.value = '';
        display.placeholder = 'Select a product';
    }
}
</script>

<?php include '../includes/footer.php'; ?>