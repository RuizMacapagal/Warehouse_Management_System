<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is admin
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get dashboard statistics
$stats = [
    'products' => 0,
    'orders' => 0,
    'customers' => 0,
    'sales' => 0,
    'low_stock' => 0
];

// Count products
$query = "SELECT COUNT(*) as count FROM product";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $stats['products'] = $row['count'];
}

// Count orders
$query = "SELECT COUNT(*) as count FROM orders";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $stats['orders'] = $row['count'];
}

// Count customers
$query = "SELECT COUNT(*) as count FROM customer";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $stats['customers'] = $row['count'];
}

// Sum sales
$query = "SELECT SUM(Subtotal) as total FROM sales";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $stats['sales'] = $row['total'] ? $row['total'] : 0;
}

// Count low stock items (using case-level stock)
$query = "SELECT COUNT(*) as count FROM product WHERE StockOnHandPerCase <= ReorderPoint";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $stats['low_stock'] = $row['count'];
}

// Get recent orders
$recentOrders = [];
$query = "SELECT o.OrderID, c.CustomerName, o.OrderDate, o.OrderStatus, o.Subtotal 
          FROM orders o 
          JOIN customer c ON o.CustomerID = c.CustomerID 
          ORDER BY o.OrderDate DESC LIMIT 5";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentOrders[] = $row;
    }
}

// Get low stock products (case-level stock for display)
$lowStockProducts = [];
$query = "SELECT ProductID, ProductName, StockOnHandPerCase, ReorderPoint, QtyPerCase 
          FROM product 
          WHERE StockOnHandPerCase <= ReorderPoint 
          ORDER BY StockOnHandPerCase ASC LIMIT 5";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $lowStockProducts[] = $row;
    }
}

// Build dynamic data for charts
// Monthly sales for last 12 months
$monthlyLabels = [];
$monthlySalesValues = [];
$now = new DateTime();
$monthKeys = [];
for ($i = 11; $i >= 0; $i--) {
    $dt = (clone $now)->modify("-{$i} months");
    $monthlyLabels[] = $dt->format('M');
    $monthKeys[] = $dt->format('Y-m');
}

$salesMap = array_fill_keys($monthKeys, 0);
$salesSql = "SELECT DATE_FORMAT(OrderDate, '%Y-%m') as ym, SUM(Subtotal) as total
             FROM orders
             WHERE OrderDate >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
             GROUP BY ym";
$salesRes = $conn->query($salesSql);
if ($salesRes) {
    while ($r = $salesRes->fetch_assoc()) {
        $key = $r['ym'];
        $salesMap[$key] = (float)$r['total'];
    }
}
foreach ($monthKeys as $k) { $monthlySalesValues[] = isset($salesMap[$k]) ? $salesMap[$k] : 0; }

// Real-time product brand totals (stock-based)
$categoryLabels = ['Coca-Cola','Sprite','Royal','Wilkins','San Miguel Beer'];
$categoryTotals = [0,0,0,0,0];
$brandSql = "SELECT 
    SUM(CASE WHEN ProductName LIKE '%Coca-Cola%' THEN StockOnHandPerCase ELSE 0 END) as coca,
    SUM(CASE WHEN ProductName LIKE '%Sprite%' THEN StockOnHandPerCase ELSE 0 END) as sprite,
    SUM(CASE WHEN ProductName LIKE '%Royal%' THEN StockOnHandPerCase ELSE 0 END) as royal,
    SUM(CASE WHEN ProductName LIKE '%Wilkins%' THEN StockOnHandPerCase ELSE 0 END) as wilkins,
    SUM(CASE WHEN ProductName LIKE '%San Miguel%' THEN StockOnHandPerCase ELSE 0 END) as smb
    FROM product";
$brandRes = $conn->query($brandSql);
if ($brandRes && $brandRow = $brandRes->fetch_assoc()) {
    $categoryTotals = [
        (int)$brandRow['coca'],
        (int)$brandRow['sprite'],
        (int)$brandRow['royal'],
        (int)$brandRow['wilkins'],
        (int)$brandRow['smb']
    ];
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
            <a class="nav-link active" href="dashboard.php">
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
            <div class="col-md-12">
                <h2 class="mb-4">Admin Dashboard</h2>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Welcome back, <?php echo $_SESSION['username']; ?>! Here's what's happening in your warehouse today.
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
                            <h2 class="mb-0"><?php echo $stats['products']; ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="dashboard-card card bg-success text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Total Sales</h6>
                            <h2 class="mb-0 sales-amount">₱<?php echo number_format($stats['sales'], 2); ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <style>
            .sales-amount {
                min-width: 120px;
                text-align: left;
            }
            </style>
            <div class="col-md-3 mb-3">
                <div class="dashboard-card card bg-info text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Total Orders</h6>
                            <h2 class="mb-0"><?php echo $stats['orders']; ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-shopping-cart"></i>
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
        </div>

        <div class="row dashboard-tables-row">
            <!-- Recent Orders Table -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Recent Orders</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentOrders)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No recent orders found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentOrders as $order): ?>
                                            <tr>
                                                <td><a href="orders.php?id=<?php echo $order['OrderID']; ?>">#<?php echo $order['OrderID']; ?></a></td>
                                                <td><?php echo $order['CustomerName']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($order['OrderDate'])); ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($order['OrderStatus']) {
                                                        case 'Pending':
                                                            $statusClass = 'badge bg-warning';
                                                            break;
                                                        case 'Processing':
                                                            $statusClass = 'badge bg-info';
                                                            break;
                                                        case 'Completed':
                                                            $statusClass = 'badge bg-success';
                                                            break;
                                                        case 'Cancelled':
                                                            $statusClass = 'badge bg-danger';
                                                            break;
                                                        default:
                                                            $statusClass = 'badge bg-secondary';
                                                    }
                                                    ?>
                                                    <span class="<?php echo $statusClass; ?>"><?php echo $order['OrderStatus']; ?></span>
                                                </td>
                                                <td>₱<?php echo number_format($order['Subtotal'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <a href="orders.php" class="btn btn-sm btn-primary">View All Orders</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Low Stock Products Table -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Low Stock Products</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Product ID</th>
                                        <th>Product Name</th>
                                        <th>Current Stock</th>
                                        <th>Reorder Point</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($lowStockProducts)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No low stock products found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($lowStockProducts as $product): ?>
                                            <tr>
                                                <td>#<?php echo $product['ProductID']; ?></td>
                                                <td><?php echo $product['ProductName']; ?></td>
                                                <td>
                                                    <?php 
                                                    $stockCases = (int)$product['StockOnHandPerCase'];
                                                     if ($stockCases <= 0): ?>
                                                         <span class="badge bg-danger">Out of Stock</span>
                                                     <?php else: ?>
                                                         <span class="badge bg-warning"><?php echo number_format($stockCases); ?> cases</span>
                                                     <?php endif; ?>
                                                </td>
                                                <td><?php echo $product['ReorderPoint']; ?></td>
                                                <td>
                                                    <a href="products.php?id=<?php echo $product['ProductID']; ?>" class="btn btn-sm btn-primary">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <a href="products.php?filter=low_stock" class="btn btn-sm btn-primary">View All Low Stock</a>
                        </div>
                    </div>
                </div>
         </div>
         
         <!-- Monthly Sales Chart - Centered Below Tables -->
         <div class="row justify-content-center">
             <div class="col-md-10 mb-4">
                 <div class="card">
                     <div class="card-header">
                         <h5 class="card-title">Monthly Sales</h5>
                     </div>
                     <div class="card-body">
                         <canvas id="salesChart" width="400" height="200"></canvas>
                     </div>
                 </div>
             </div>
         </div>
     </div>
 </div>
</div>

<script>
// Sample data for charts
document.addEventListener('DOMContentLoaded', function() {
    // Dynamic data from PHP
    var salesLabels = <?php echo json_encode($monthlyLabels); ?>;
    var salesValues = <?php echo json_encode($monthlySalesValues); ?>;
    var categoryLabels = <?php echo json_encode($categoryLabels); ?>;
    var categoryValues = <?php echo json_encode($categoryTotals); ?>;

    // Sales Chart (real-time)
    var salesCtx = document.getElementById('salesChart').getContext('2d');
    var salesChart = new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: salesLabels,
            datasets: [{
                label: 'Monthly Sales (₱)',
                data: salesValues,
                backgroundColor: 'rgba(220, 20, 60, 0.2)',
                borderColor: 'rgba(220, 20, 60, 1)',
                borderWidth: 2,
                tension: 0.35,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return '₱' + value.toLocaleString(); }
                    }
                }
            }
        }
    });

    // Product Categories chart removed
});
</script>


<?php include '../includes/footer.php'; ?>