<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is admin
if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle product actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Delete product
    if ($action == 'delete' && isset($_GET['id'])) {
        $productId = $_GET['id'];
        $query = "DELETE FROM product WHERE ProductID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $productId);
        
        if ($stmt->execute()) {
            $successMsg = "Product deleted successfully";
        } else {
            $errorMsg = "Error deleting product: " . $conn->error;
        }
    }
}

// Get all products
$products = [];
$query = "SELECT * FROM product ORDER BY ProductName";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Get product categories for filter
$categories = []; // Using free-text filter; no category table in schema
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
            <a class="nav-link active" href="products.php">
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
            <div class="col-md-8">
                <h2>Product Management</h2>
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="fas fa-plus"></i> Add New Product
                </button>
            </div>
        </div>

        <?php if (isset($successMsg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $successMsg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $errorMsg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label for="category" class="form-label">Category</label>
                        <input type="text" class="form-control" id="category" name="category" placeholder="e.g., Soda, Water">
                    </div>
                    <div class="col-md-4">
                        <label for="stock" class="form-label">Stock Status</label>
                        <select class="form-select" id="stock" name="stock">
                            <option value="">All</option>
                            <option value="low">Low Stock</option>
                            <option value="out">Out of Stock</option>
                            <option value="in">In Stock</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="search" name="search" placeholder="Search products...">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable" id="productsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Reorder Point</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No products found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>#<?php echo $product['ProductID']; ?></td>
                                        <td><?php echo $product['ProductName']; ?></td>
                                        <td><?php echo $product['ProductCategory']; ?></td>
                                        <td>₱<?php echo number_format($product['ProductPrice'], 2); ?></td>
                                        <td><?php echo $product['StockOnHandPerCase']; ?></td>
                                        <td><?php echo $product['ReorderPoint']; ?></td>
                                        <td>
                                            <?php 
                                            $stockCases = (int)$product['StockOnHandPerCase'];
                                            if ($stockCases <= 0): ?>
                                                <span class="badge bg-danger">0 cases</span>
                                            <?php elseif ($stockCases <= (int)$product['ReorderPoint']): ?>
                                                <span class="badge bg-warning">Low Stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">In Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="products.php?action=delete&id=<?php echo $product['ProductID']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this product?')">
                                                <i class="fas fa-trash"></i>
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

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="product_process.php" method="POST">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="productName" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="productName" name="productName" required>
                        </div>
                        <div class="col-md-6">
                            <label for="productCategory" class="form-label">Category</label>
                            <input type="text" class="form-control" id="productCategory" name="productCategory" placeholder="e.g., Soda, Water" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="productPrice" class="form-label">Unit Price</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" class="form-control" id="productPrice" name="productPrice" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="qtyPerCase" class="form-label">Qty Per Case</label>
                            <input type="number" class="form-control" id="qtyPerCase" name="qtyPerCase" value="12" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="stockOnHandPerCase" class="form-label">Stock On Hand (Cases)</label>
                            <input type="number" class="form-control" id="stockOnHandPerCase" name="stockOnHandPerCase" required>
                        </div>
                        <div class="col-md-6">
                            <label for="reorderPoint" class="form-label">Reorder Point (Cases)</label>
                            <input type="number" class="form-control" id="reorderPoint" name="reorderPoint" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>