<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is cashier or admin
if ($_SESSION['role'] != 'cashier' && $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get date range from form or set defaults
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Validate dates
if (!$startDate) $startDate = date('Y-m-01');
if (!$endDate) $endDate = date('Y-m-d');

// Get sales summary for the date range
$summaryQuery = "SELECT 
    COUNT(o.OrderID) as total_orders,
    COALESCE(SUM(o.Subtotal), 0) as total_sales,
    COALESCE(AVG(o.Subtotal), 0) as avg_order_value,
    COUNT(DISTINCT o.CustomerID) as unique_customers
    FROM orders o 
    WHERE DATE(o.OrderDate) BETWEEN ? AND ?";

$stmt = $conn->prepare($summaryQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Get daily sales data for chart
$dailySalesQuery = "SELECT 
    DATE(OrderDate) as sale_date,
    COUNT(OrderID) as orders_count,
    SUM(Subtotal) as daily_sales
    FROM orders 
    WHERE DATE(OrderDate) BETWEEN ? AND ?
    GROUP BY DATE(OrderDate)
    ORDER BY sale_date";

$stmt = $conn->prepare($dailySalesQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$dailySalesResult = $stmt->get_result();
$dailySales = [];
while ($row = $dailySalesResult->fetch_assoc()) {
    $dailySales[] = $row;
}

// Get top selling products
$topProductsQuery = "SELECT 
    p.ProductName,
    p.ProductPrice,
    SUM(od.QuantityOrdered) as total_sold,
    SUM(od.QuantityOrdered * od.ProductPrice) as total_revenue
    FROM order_details od
    JOIN product p ON od.ProductID = p.ProductID
    JOIN orders o ON od.OrderID = o.OrderID
    WHERE DATE(o.OrderDate) BETWEEN ? AND ?
    GROUP BY p.ProductID, p.ProductName, p.ProductPrice
    ORDER BY total_sold DESC
    LIMIT 10";

$stmt = $conn->prepare($topProductsQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$topProductsResult = $stmt->get_result();
$topProducts = [];
while ($row = $topProductsResult->fetch_assoc()) {
    $topProducts[] = $row;
}

// Get recent orders
$recentOrdersQuery = "SELECT 
    o.OrderID,
    o.OrderDate,
    o.Subtotal,
    o.OrderStatus,
    c.CustomerName as customer_name
    FROM orders o
    LEFT JOIN customer c ON o.CustomerID = c.CustomerID
    WHERE DATE(o.OrderDate) BETWEEN ? AND ?
    ORDER BY o.OrderDate DESC
    LIMIT 20";

$stmt = $conn->prepare($recentOrdersQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$recentOrdersResult = $stmt->get_result();
$recentOrders = [];
while ($row = $recentOrdersResult->fetch_assoc()) {
    $recentOrders[] = $row;
}

// Get hourly sales pattern (for today only if end date is today)
$hourlySales = [];
if ($endDate === date('Y-m-d')) {
    $hourlyQuery = "SELECT 
        HOUR(OrderDate) as hour,
        COUNT(OrderID) as orders_count,
        SUM(Subtotal) as hourly_sales
        FROM orders 
        WHERE DATE(OrderDate) = CURDATE()
        GROUP BY HOUR(OrderDate)
        ORDER BY hour";
    
    $hourlyResult = $conn->query($hourlyQuery);
    while ($row = $hourlyResult->fetch_assoc()) {
        $hourlySales[] = $row;
    }
}
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
            <a class="nav-link" href="customers.php">
                <i class="fas fa-users"></i> Customers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="reports.php">
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
            <div class="col-md-12">
                <h2 class="mb-0">Sales Reports</h2>
                <p class="text-muted">Sales analytics and performance metrics</p>
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
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i>Filter Reports
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-shopping-cart fa-2x text-primary mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($summary['total_orders']); ?></h4>
                        <p class="text-muted mb-0">Total Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-peso-sign fa-2x text-success mb-2"></i>
                        <h4 class="mb-1">₱<?php echo number_format($summary['total_sales'], 2); ?></h4>
                        <p class="text-muted mb-0">Total Sales</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-chart-line fa-2x text-info mb-2"></i>
                        <h4 class="mb-1">₱<?php echo number_format($summary['avg_order_value'], 2); ?></h4>
                        <p class="text-muted mb-0">Avg Order Value</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-warning mb-2"></i>
                        <h4 class="mb-1"><?php echo number_format($summary['unique_customers']); ?></h4>
                        <p class="text-muted mb-0">Unique Customers</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Daily Sales Chart -->
            <div class="col-md-8 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daily Sales Trend</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="dailySalesChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Products -->
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Top Selling Products</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topProducts)): ?>
                            <div class="text-center text-muted">No sales data available</div>
                        <?php else: ?>
                            <?php foreach ($topProducts as $index => $product): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($product['ProductName']); ?></div>
                                        <small class="text-muted">₱<?php echo number_format($product['ProductPrice'], 2); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-primary"><?php echo $product['total_sold']; ?> sold</div>
                                        <small class="text-muted">₱<?php echo number_format($product['total_revenue'], 2); ?></small>
                                    </div>
                                </div>
                                <?php if ($index < count($topProducts) - 1): ?>
                                    <hr class="my-2">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($hourlySales)): ?>
        <!-- Hourly Sales Pattern (Today Only) -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Today's Hourly Sales Pattern</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="hourlySalesChart" height="50"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Orders -->
        <div class="card dashboard-card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Orders</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date & Time</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No orders found for the selected period</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['OrderID']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Walk-in Customer'); ?></td>
                                    <td><?php echo date('M d, Y g:i A', strtotime($order['OrderDate'])); ?></td>
                                    <td>₱<?php echo number_format($order['Subtotal'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo strtolower($order['OrderStatus']) === 'completed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($order['OrderStatus']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="receipt.php?order_id=<?php echo $order['OrderID']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-receipt"></i>
                                        </a>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daily Sales Chart
const dailySalesData = <?php echo json_encode($dailySales); ?>;
const dailyLabels = dailySalesData.map(item => {
    const date = new Date(item.sale_date);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
});
const dailySalesValues = dailySalesData.map(item => parseFloat(item.daily_sales));

const dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
new Chart(dailySalesCtx, {
    type: 'line',
    data: {
        labels: dailyLabels,
        datasets: [{
            label: 'Daily Sales (₱)',
            data: dailySalesValues,
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
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

<?php if (!empty($hourlySales)): ?>
// Hourly Sales Chart (Today Only)
const hourlySalesData = <?php echo json_encode($hourlySales); ?>;
const hourlyLabels = [];
const hourlySalesValues = [];

// Fill in all 24 hours
for (let i = 0; i < 24; i++) {
    const hour = i === 0 ? '12 AM' : i < 12 ? i + ' AM' : i === 12 ? '12 PM' : (i - 12) + ' PM';
    hourlyLabels.push(hour);
    
    const hourData = hourlySalesData.find(item => parseInt(item.hour) === i);
    hourlySalesValues.push(hourData ? parseFloat(hourData.hourly_sales) : 0);
}

const hourlySalesCtx = document.getElementById('hourlySalesChart').getContext('2d');
new Chart(hourlySalesCtx, {
    type: 'bar',
    data: {
        labels: hourlyLabels,
        datasets: [{
            label: 'Hourly Sales (₱)',
            data: hourlySalesValues,
            backgroundColor: 'rgba(40, 167, 69, 0.8)',
            borderColor: '#28a745',
            borderWidth: 1
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
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
