<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is inventory
if ($_SESSION['role'] != 'inventory' && $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get inventory statistics
$stats = [
    'total_products' => 0,
    'low_stock' => 0,
    'out_of_stock' => 0,
    'total_value' => 0
];

// Count total products
$query = "SELECT COUNT(*) as count FROM product";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_products'] = $row['count'];
}

// Count low stock products
$query = "SELECT COUNT(*) as count FROM product WHERE StockOnHandPerCase <= ReorderPoint AND StockOnHandPerCase > 0";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $stats['low_stock'] = $row['count'];
}

// Count out of stock products
$query = "SELECT COUNT(*) as count FROM product WHERE StockOnHandPerCase = 0";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $stats['out_of_stock'] = $row['count'];
}

// Calculate total inventory value
$query = "SELECT SUM(StockOnHandPerCase * QtyPerCase * ProductPrice) as total FROM product";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_value'] = $row['total'] ? $row['total'] : 0;
}

// Get all products for inventory table
$products = [];
$query = "SELECT * FROM product ORDER BY ProductCategory, ProductName";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-warehouse"></i> 8JJ's Trading Incorporation</h3>
        <p>Inventory Management</p>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link active" href="dashboard.php">
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
                <i class="fas fa-truck-loading"></i> Receive Stock
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
                <h2 class="mb-4">Inventory Dashboard</h2>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Welcome, <?php echo $_SESSION['username']; ?>! Manage your warehouse inventory efficiently.
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="dashboard-card card bg-primary text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Total Products</h6>
                            <h2 class="mb-0"><?php echo $stats['total_products']; ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="dashboard-card card bg-warning text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Low Stock Items</h6>
                            <h2 class="mb-0"><?php echo $stats['low_stock']; ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="dashboard-card card bg-danger text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Out of Stock</h6>
                            <h2 class="mb-0"><?php echo $stats['out_of_stock']; ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="dashboard-card card bg-success text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Inventory Value</h6>
                            <h2 class="mb-0">₱<?php echo number_format($stats['total_value'], 2); ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="receive.php" class="btn btn-primary w-100 py-3">
                                    <i class="fas fa-truck-loading me-2"></i> Receive Stock
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="stock.php?filter=low_stock" class="btn btn-warning w-100 py-3">
                                    <i class="fas fa-exclamation-triangle me-2"></i> View Low Stock
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="stock.php?action=add" class="btn btn-success w-100 py-3">
                                    <i class="fas fa-plus me-2"></i> Add New Product
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="reports.php" class="btn btn-info w-100 py-3">
                                    <i class="fas fa-chart-bar me-2"></i> Generate Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="card dashboard-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Current Inventory</h5>
                        <div>
                            <a href="stock.php" class="btn btn-sm btn-primary">Manage Stock</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover datatable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Cases</th>
                                        <th>Qty/Case</th>
                                        <th>Total Pieces</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?php echo $product['ProductID']; ?></td>
                                        <td><?php echo $product['ProductName']; ?></td>
                                        <td><?php echo $product['ProductCategory']; ?></td>
                                        <td>₱<?php echo number_format($product['ProductPrice'], 2); ?></td>
                                        <?php 
                                            $qtyCase = max(1, (int)$product['QtyPerCase']);
                                            $casesComputed = (int)$product['StockOnHandPerCase'];
                                            $stockPiecesForCases = $casesComputed * $qtyCase;
                                        ?>
                                        <td><?php echo $casesComputed; ?></td>
                                        <td><?php echo $product['QtyPerCase']; ?></td>
                                        <td><?php echo $stockPiecesForCases; ?></td>
                                        <td>
                                            <?php 
                                            if ($casesComputed == 0) {
                                                echo '<span class="badge bg-danger">Out of Stock</span>';
                                            } elseif ($casesComputed <= $product['ReorderPoint']) {
                                                echo '<span class="badge bg-warning">Low Stock</span>';
                                            } else {
                                                echo '<span class="badge bg-success">In Stock</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="stock.php?action=edit&id=<?php echo $product['ProductID']; ?>" class="btn btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="receive.php?id=<?php echo $product['ProductID']; ?>" class="btn btn-success">
                                                    <i class="fas fa-plus"></i>
                                                </a>
                                            </div>
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
    </div>
</div>

<?php include '../includes/footer.php'; ?>
