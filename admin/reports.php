<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is admin
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get date range from request or default to last 30 days
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Sales Report Data
$salesQuery = "SELECT 
    DATE(o.OrderDate) as date,
    COUNT(o.OrderID) as orders_count,
    SUM(o.Subtotal) as total_sales
    FROM orders o 
    WHERE DATE(o.OrderDate) BETWEEN ? AND ?
    GROUP BY DATE(o.OrderDate)
    ORDER BY date DESC";

$salesStmt = $conn->prepare($salesQuery);
$salesStmt->bind_param("ss", $startDate, $endDate);
$salesStmt->execute();
$salesResult = $salesStmt->get_result();
$salesData = [];
while ($row = $salesResult->fetch_assoc()) {
    $salesData[] = $row;
}

// Top Products Report
$topProductsQuery = "SELECT 
    p.ProductName,
    SUM(od.QuantityOrdered) as total_sold,
    SUM(od.QuantityOrdered * od.ProductPrice) as total_revenue
    FROM order_details od
    JOIN product p ON od.ProductID = p.ProductID
    JOIN orders o ON od.OrderID = o.OrderID
    WHERE DATE(o.OrderDate) BETWEEN ? AND ?
    GROUP BY p.ProductID, p.ProductName
    ORDER BY total_sold DESC
    LIMIT 10";

$topProductsStmt = $conn->prepare($topProductsQuery);
$topProductsStmt->bind_param("ss", $startDate, $endDate);
$topProductsStmt->execute();
$topProductsResult = $topProductsStmt->get_result();
$topProducts = [];
while ($row = $topProductsResult->fetch_assoc()) {
    $topProducts[] = $row;
}

// Customer Report
$customerQuery = "SELECT 
    c.CustomerName,
    COUNT(o.OrderID) as order_count,
    SUM(o.Subtotal) as total_spent
    FROM customer c
    LEFT JOIN orders o ON c.CustomerID = o.CustomerID AND DATE(o.OrderDate) BETWEEN ? AND ?
    GROUP BY c.CustomerID, c.CustomerName
    HAVING order_count > 0
    ORDER BY total_spent DESC
    LIMIT 10";

$customerStmt = $conn->prepare($customerQuery);
$customerStmt->bind_param("ss", $startDate, $endDate);
$customerStmt->execute();
$customerResult = $customerStmt->get_result();
$topCustomers = [];
while ($row = $customerResult->fetch_assoc()) {
    $topCustomers[] = $row;
}

// Summary Statistics
$summaryQuery = "SELECT 
    COUNT(DISTINCT o.OrderID) as total_orders,
    SUM(o.Subtotal) as total_revenue,
    AVG(o.Subtotal) as avg_order_value,
    COUNT(DISTINCT o.CustomerID) as unique_customers
    FROM orders o
    WHERE DATE(o.OrderDate) BETWEEN ? AND ?";

$summaryStmt = $conn->prepare($summaryQuery);
$summaryStmt->bind_param("ss", $startDate, $endDate);
$summaryStmt->execute();
$summaryResult = $summaryStmt->get_result();
$summary = $summaryResult->fetch_assoc();

// Low Stock Products (use case-level stock)
$lowStockQuery = "SELECT ProductName, StockOnHandPerCase FROM product WHERE StockOnHandPerCase < ReorderPoint ORDER BY StockOnHandPerCase ASC LIMIT 10";
$lowStockResult = $conn->query($lowStockQuery);
$lowStockProducts = [];
while ($row = $lowStockResult->fetch_assoc()) {
    $lowStockProducts[] = $row;
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
            <a class="nav-link active" href="reports.php">
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
                <h2 class="mb-0">Reports & Analytics</h2>
                <form method="GET" class="d-flex gap-2">
                    <input type="date" class="form-control" name="start_date" value="<?php echo $startDate; ?>">
                    <input type="date" class="form-control" name="end_date" value="<?php echo $endDate; ?>">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-shopping-cart fa-2x text-primary mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($summary['total_orders'] ?? 0); ?></h4>
                        <p class="text-muted mb-0">Total Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                        <h4 class="mb-1">₱<?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></h4>
                        <p class="text-muted mb-0">Total Revenue</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-chart-line fa-2x text-info mb-2"></i>
                        <h4 class="mb-1">₱<?php echo number_format($summary['avg_order_value'] ?? 0, 2); ?></h4>
                        <p class="text-muted mb-0">Avg Order Value</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-warning mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($summary['unique_customers'] ?? 0); ?></h4>
                        <p class="text-muted mb-0">Unique Customers</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sales Chart -->
            <div class="col-md-8">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Sales Trend</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Low Stock Alert</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($lowStockProducts)): ?>
                            <p class="text-muted">No low stock items</p>
                        <?php else: ?>
                            <?php foreach ($lowStockProducts as $product): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?php echo htmlspecialchars($product['ProductName']); ?></span>
                                    <span class="badge bg-danger"><?php echo number_format((int)$product['StockOnHandPerCase']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Top Products -->
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Top Selling Products</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Sold</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topProducts as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['ProductName']); ?></td>
                                        <td><?php echo $product['total_sold']; ?></td>
                                        <td>₱<?php echo number_format($product['total_revenue'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Customers -->
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Top Customers</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Orders</th>
                                        <th>Total Spent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topCustomers as $customer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($customer['CustomerName']); ?></td>
                                        <td><?php echo $customer['order_count']; ?></td>
                                        <td>₱<?php echo number_format($customer['total_spent'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Sales Table -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daily Sales Report</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Orders</th>
                                        <th>Total Sales</th>
                                        <th>Average Order</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($salesData as $sale): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($sale['date'])); ?></td>
                                        <td><?php echo $sale['orders_count']; ?></td>
                                        <td>₱<?php echo number_format($sale['total_sales'], 2); ?></td>
                                        <td>₱<?php echo number_format($sale['total_sales'] / $sale['orders_count'], 2); ?></td>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Sales Chart
const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesData = <?php echo json_encode(array_reverse($salesData)); ?>;

new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: salesData.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }),
        datasets: [{
            label: 'Daily Sales',
            data: salesData.map(item => parseFloat(item.total_sales)),
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₱' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Sales: ₱' + context.parsed.y.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
