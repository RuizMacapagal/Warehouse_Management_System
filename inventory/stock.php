<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is inventory or admin
if ($_SESSION['role'] != 'inventory' && $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle stock operations
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_stock'])) {
        $productId = $_POST['product_id'];
        $newStock = $_POST['new_stock'];
        $reason = trim($_POST['reason']);
        
        if ($productId && is_numeric($newStock) && $newStock >= 0) {
            // Get current stock (cases)
            $currentStockQuery = "SELECT StockOnHandPerCase, ProductName FROM product WHERE ProductID = ?";
            $stmt = $conn->prepare($currentStockQuery);
            $stmt->bind_param("s", $productId);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            
            if ($product) {
                $oldStock = $product['StockOnHandPerCase'];
                $difference = $newStock - $oldStock;
                
                // Update stock (cases)
                $updateStmt = $conn->prepare("UPDATE product SET StockOnHandPerCase = ? WHERE ProductID = ?");
                $updateStmt->bind_param("is", $newStock, $productId);
                
                if ($updateStmt->execute()) {
                    // Movement logging disabled for legacy schema without stock_movements
                    $message = "Stock updated successfully.";
                    $messageType = "success";
                } else {
                    $message = "Failed to update stock.";
                    $messageType = "danger";
                }
            }
        }
    }

    if (isset($_POST['bulk_update'])) {
        $updates = $_POST['stock_updates'] ?? [];
        $successCount = 0;
        foreach ($updates as $productId => $newStock) {
            $newStock = (int)$newStock;
            if ($newStock < 0) continue;
            
            // Get current stock (cases)
            $stmt = $conn->prepare("SELECT StockOnHandPerCase FROM product WHERE ProductID = ?");
            $stmt->bind_param("s", $productId);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            
            if ($product) {
                $oldStock = (int)$product['StockOnHandPerCase'];
                $difference = $newStock - $oldStock;
                
                if ($difference != 0) {
                    // Update stock (cases)
                    $updateStmt = $conn->prepare("UPDATE product SET StockOnHandPerCase = ? WHERE ProductID = ?");
                    $updateStmt->bind_param("is", $newStock, $productId);
                    
                    if ($updateStmt->execute()) {
                        // Movement logging disabled for legacy schema without stock_movements
                        $successCount++;
                    }
                }
            }
        }
        
        if ($successCount > 0) {
            $message = "Successfully updated stock for $successCount products.";
            $messageType = "success";
        } else {
            $message = "No stock updates were made.";
            $messageType = "info";
        }
    }
}

// Get search and filter parameters
$searchFilter = $_GET['search'] ?? '';
$stockFilter = $_GET['stock_filter'] ?? 'all';

// Build query with filters
$whereClause = "WHERE 1=1";
$params = [];
$types = '';

if ($searchFilter) {
    $whereClause .= " AND (ProductName LIKE ? OR ProductID LIKE ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
    $types .= 'ss';
}

if ($stockFilter === 'low') {
    $whereClause .= " AND StockOnHandPerCase <= ReorderPoint AND StockOnHandPerCase > 0";
} elseif ($stockFilter === 'out') {
    $whereClause .= " AND StockOnHandPerCase = 0";
} elseif ($stockFilter === 'good') {
    $whereClause .= " AND StockOnHandPerCase > ReorderPoint";
}

// Get products with stock information (cases)
$query = "SELECT ProductID, ProductName, StockOnHandPerCase, ReorderPoint, ProductPrice, QtyPerCase 
          FROM product 
          $whereClause 
          ORDER BY ProductName";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

// Get stock statistics (cases)
$statsQuery = "SELECT 
    COUNT(*) as total_products,
    COUNT(CASE WHEN StockOnHandPerCase <= ReorderPoint AND StockOnHandPerCase > 0 THEN 1 END) as low_stock,
    COUNT(CASE WHEN StockOnHandPerCase = 0 THEN 1 END) as out_of_stock,
    SUM(StockOnHandPerCase * ProductPrice) as total_value
    FROM product";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Get recent stock movements (disabled in legacy schema)
$recentMovements = [];
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
            <a class="nav-link active" href="stock.php">
                <i class="fas fa-boxes"></i> Stock Management
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="receive.php">
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
            <div class="col-md-12 d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Stock Management</h2>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                    <i class="fas fa-edit me-2"></i>Bulk Update
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
                        <i class="fas fa-boxes fa-2x text-primary mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($stats['total_products']); ?></h4>
                        <p class="text-muted mb-0">Total Products</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($stats['low_stock']); ?></h4>
                        <p class="text-muted mb-0">Low Stock</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($stats['out_of_stock']); ?></h4>
                        <p class="text-muted mb-0">Out of Stock</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-peso-sign fa-2x text-success mb-2"></i>
                        <h4 class="mb-1">₱<?php echo number_format($stats['total_value'], 2); ?></h4>
                        <p class="text-muted mb-0">Total Value</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Stock Management -->
            <div class="col-md-8 mb-4">
                <!-- Filters -->
                <div class="card dashboard-card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo htmlspecialchars($searchFilter); ?>">
                            </div>
                            <div class="col-md-4">
                                <select name="stock_filter" class="form-select">
                                    <option value="all" <?php echo $stockFilter === 'all' ? 'selected' : ''; ?>>All Products</option>
                                    <option value="low" <?php echo $stockFilter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                                    <option value="out" <?php echo $stockFilter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                                    <option value="good" <?php echo $stockFilter === 'good' ? 'selected' : ''; ?>>Good Stock</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Product Stock Levels</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Current Stock (cases)</th>
                                        <th>Reorder Level</th>
                                        <th>Status</th>
                                        <th>Value</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No products found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($product['ProductName']); ?></strong>
                                                    <br><small class="text-muted">ID: <?php echo htmlspecialchars($product['ProductID']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="fw-bold"><?php echo number_format($product['StockOnHandPerCase']); ?></span>
                                            </td>
                                            <td><?php echo number_format($product['ReorderPoint']); ?></td>
                                            <td>
                                                <?php if ($product['StockOnHandPerCase'] == 0): ?>
                                                    <span class="badge bg-danger">Out of Stock</span>
                                                <?php elseif ($product['StockOnHandPerCase'] <= $product['ReorderPoint']): ?>
                                                    <span class="badge bg-warning">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Good Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>₱<?php echo number_format($product['StockOnHandPerCase'] * $product['ProductPrice'], 2); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="updateStock('<?php echo $product['ProductID']; ?>', '<?php echo htmlspecialchars($product['ProductName']); ?>', <?php echo (int)$product['StockOnHandPerCase']; ?>)">
                                                    <i class="fas fa-edit"></i>
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

            <!-- Recent Stock Movements -->
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recent Stock Movements</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentMovements)): ?>
                            <div class="text-center text-muted">No recent movements</div>
                        <?php else: ?>
                            <?php foreach ($recentMovements as $movement): ?>
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?php echo htmlspecialchars($movement['product_name']); ?></div>
                                        <small class="text-muted">
                                            <?php echo $movement['movement_type'] === 'in' ? '+' : '-'; ?><?php echo $movement['quantity']; ?> units
                                            <br>by <?php echo htmlspecialchars($movement['username']); ?>
                                            <br><?php echo date('M d, Y g:i A', strtotime($movement['created_at'])); ?>
                                        </small>
                                        <?php if ($movement['reason']): ?>
                                            <br><small class="text-info"><?php echo htmlspecialchars($movement['reason']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge bg-<?php echo $movement['movement_type'] === 'in' ? 'success' : 'danger'; ?>">
                                        <?php echo strtoupper($movement['movement_type']); ?>
                                    </span>
                                </div>
                                <hr class="my-2">
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Stock Modal -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Update Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="product_id" id="update_product_id">
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <input type="text" class="form-control" id="update_product_name" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Stock</label>
                        <input type="text" class="form-control" id="current_stock" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Stock Quantity <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="new_stock" id="new_stock" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Change</label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Optional reason for stock adjustment"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_stock" class="btn btn-primary">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Update Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Stock Update</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason for Changes</label>
                        <textarea class="form-control" name="bulk_reason" rows="2" placeholder="Reason for bulk stock update"></textarea>
                    </div>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Current</th>
                                    <th>New Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <small>
                                            <strong><?php echo htmlspecialchars($product['ProductName']); ?></strong>
                                            <br>ID: <?php echo htmlspecialchars($product['ProductID']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo number_format($product['StockOnHandPerCase']); ?></span>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm"
                                               name="stock_updates[<?php echo $product['ProductID']; ?>]"
                                               value="<?php echo $product['StockOnHandPerCase']; ?>"
                                               min="0">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="bulk_update" class="btn btn-success">Update All</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateStock(productId, productName, currentStock) {
    document.getElementById('update_product_id').value = productId;
    document.getElementById('update_product_name').value = productName;
    document.getElementById('current_stock').value = currentStock;
    document.getElementById('new_stock').value = currentStock;
    
    const modal = new bootstrap.Modal(document.getElementById('updateStockModal'));
    modal.show();
}
</script>

<?php include '../includes/footer.php'; ?>