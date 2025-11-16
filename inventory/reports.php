<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is inventory or admin
if ($_SESSION['role'] != 'inventory' && $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get date range from request
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Validate dates
if (!$startDate) $startDate = date('Y-m-01');
if (!$endDate) $endDate = date('Y-m-d');

// Get inventory summary
$summaryQuery = "SELECT 
    COUNT(*) as total_products,
    SUM(StockOnHandPerPiece) as total_stock,
    SUM(StockOnHandPerPiece * ProductPrice) as total_value,
    SUM(CASE WHEN StockOnHandPerPiece <= ReorderPoint THEN 1 ELSE 0 END) as low_stock_items,
    SUM(CASE WHEN StockOnHandPerPiece = 0 THEN 1 ELSE 0 END) as out_of_stock_items
    FROM product";
$summaryResult = $conn->query($summaryQuery);
$summary = $summaryResult->fetch_assoc();

// Legacy schema: stock movements table not available
$movements = [];

// Legacy schema: stock_receipts table not available
$topReceived = [];

// Legacy schema: suppliers performance based on stock_receipts not available
$suppliers = [];

// Get low stock alerts
$lowStockQuery = "SELECT 
    ProductName AS name,
    ProductID AS sku,
    StockOnHandPerCase AS stock_quantity,
    ReorderPoint AS reorder_level
    FROM product
    WHERE StockOnHandPerCase <= ReorderPoint
    ORDER BY (StockOnHandPerCase / NULLIF(ReorderPoint, 0)) ASC
    LIMIT 20";
$lowStockResult = $conn->query($lowStockQuery);
$lowStockItems = [];
while ($row = $lowStockResult->fetch_assoc()) {
    $lowStockItems[] = $row;
}

// Legacy schema: stock_movements not available
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
            <a class="nav-link" href="suppliers.php">
                <i class="fas fa-building"></i> Suppliers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="reports.php">
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
                <h2 class="mb-0">Inventory Reports</h2>
                <p class="text-muted">Analytics and insights for inventory management</p>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="card dashboard-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Apply Filter
                        </button>
                        <a href="reports.php" class="btn btn-outline-secondary ms-2">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-boxes fa-2x text-primary mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($summary['total_products']); ?></h4>
                        <p class="text-muted mb-0">Total Products</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-cubes fa-2x text-success mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($summary['total_stock']); ?></h4>
                        <p class="text-muted mb-0">Total Stock</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-peso-sign fa-2x text-info mb-2"></i>
                        <h4 class="mb-1">₱<?php echo number_format($summary['total_value'], 0); ?></h4>
                        <p class="text-muted mb-0">Total Value</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($summary['low_stock_items']); ?></h4>
                        <p class="text-muted mb-0">Low Stock</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($summary['out_of_stock_items']); ?></h4>
                        <p class="text-muted mb-0">Out of Stock</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-calendar fa-2x text-secondary mb-2"></i>
                        <h4 class="mb-1"><?php echo count($movements); ?></h4>
                        <p class="text-muted mb-0">Active Days</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Stock Movement Chart -->
            <div class="col-md-8 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Stock Movement Trends</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="movementChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Received Products -->
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Top Received Products</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topReceived)): ?>
                            <p class="text-muted text-center">No data available for selected period</p>
                        <?php else: ?>
                            <?php foreach ($topReceived as $index => $product): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($product['sku']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-primary"><?php echo number_format($product['total_received']); ?></span>
                                        <br><small class="text-muted">₱<?php echo number_format($product['total_cost'], 2); ?></small>
                                    </div>
                                </div>
                                <?php if ($index < count($topReceived) - 1): ?>
                                    <hr class="my-2">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Supplier Performance -->
            <div class="col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Supplier Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Supplier</th>
                                        <th>Deliveries</th>
                                        <th>Total Value</th>
                                        <th>Avg Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($suppliers)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No data available</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($suppliers as $supplier): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $supplier['delivery_count']; ?></span></td>
                                            <td>₱<?php echo number_format($supplier['total_value'], 2); ?></td>
                                            <td>₱<?php echo number_format($supplier['avg_delivery_value'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Low Stock Alerts -->
            <div class="col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Low Stock Alerts</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Current</th>
                                        <th>Reorder</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($lowStockItems)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-success">
                                                <i class="fas fa-check-circle me-2"></i>All products are well stocked
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($lowStockItems as $item): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($item['sku']); ?></small>
                                            </td>
                                            <td><?php echo number_format($item['stock_quantity']); ?></td>
                                            <td><?php echo number_format($item['reorder_level']); ?></td>
                                            <td>
                                                <?php if ($item['stock_quantity'] == 0): ?>
                                                    <span class="badge bg-danger">Out of Stock</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Low Stock</span>
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

        <!-- Recent Stock Movements -->
        <div class="card dashboard-card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Stock Movements</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Product</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Reference</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentMovements)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No movements found for selected period</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentMovements as $movement): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($movement['created_at'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($movement['product_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($movement['sku']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($movement['movement_type'] == 'in'): ?>
                                            <span class="badge bg-success">Stock In</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Stock Out</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($movement['movement_type'] == 'in'): ?>
                                            <span class="text-success">+<?php echo number_format($movement['quantity']); ?></span>
                                        <?php else: ?>
                                            <span class="text-danger">-<?php echo number_format($movement['quantity']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($movement['reference'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($movement['user_name']); ?></td>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Stock Movement Chart
const movementData = <?php echo json_encode($movements); ?>;
const ctx = document.getElementById('movementChart').getContext('2d');

const labels = movementData.map(item => {
    const date = new Date(item.movement_date);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
});

const stockInData = movementData.map(item => parseInt(item.stock_in));
const stockOutData = movementData.map(item => parseInt(item.stock_out));

new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels.reverse(),
        datasets: [{
            label: 'Stock In',
            data: stockInData.reverse(),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.1,
            fill: true
        }, {
            label: 'Stock Out',
            data: stockOutData.reverse(),
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.1)',
            tension: 0.1,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>