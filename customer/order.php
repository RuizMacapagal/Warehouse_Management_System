<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Ensure only customer role can access
if ($_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit;
}

// Ensure a customer record exists for this session (defensive in case login mapping failed)
$customerID = $_SESSION['user_id'];
$customerCheck = $conn->prepare("SELECT CustomerID FROM customer WHERE CustomerID = ?");
$customerCheck->bind_param("s", $customerID);
$customerCheck->execute();
$customerRes = $customerCheck->get_result();
if (!$customerRes || $customerRes->num_rows === 0) {
    $insertCustomer = $conn->prepare("INSERT INTO customer (CustomerID, CustomerName, CustomerNumber, CustomerAddress, CustomerEmail) VALUES (?, ?, ?, ?, ?)");
    $defaultNumber = '000-000-0000';
    $defaultAddress = 'N/A';
    $email = $_SESSION['username'] . '@example.com';
    $insertCustomer->bind_param("sssss", $customerID, $_SESSION['username'], $defaultNumber, $defaultAddress, $email);
    $insertCustomer->execute();
}

// Initialize cart in session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = []; // cart items: [product_id => ['name'=>..., 'price'=>..., 'qty'=>..., 'category'=>...]]
}

$feedback = '';

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') { 
    $pid = $_POST['product_id'];  
    $name = $_POST['product_name'];
    $category = $_POST['product_category'];
    $price = (float)$_POST['product_price'];
    $qty = max(1, (int)$_POST['quantity']);

    if (isset($_SESSION['cart'][$pid])) {
        $_SESSION['cart'][$pid]['qty'] += $qty;
    } else {
        $_SESSION['cart'][$pid] = [
            'name' => $name,
            'category' => $category,
            'price' => $price,
            'qty' => $qty
        ];
    }
    $feedback = 'Item added to cart.';
}

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    foreach ($_POST['quantities'] as $pid => $qty) {
        $qty = max(0, (int)$qty);
        if ($qty === 0) {
            unset($_SESSION['cart'][$pid]);
        } else if (isset($_SESSION['cart'][$pid])) {
            $_SESSION['cart'][$pid]['qty'] = $qty;
        }
    }
    $feedback = 'Cart updated.';
}

// Handle clear cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear') {
    $_SESSION['cart'] = [];
    $feedback = 'Cart cleared.';
}

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    if (empty($_SESSION['cart'])) {
        $feedback = 'Your cart is empty.';
    } else {
        // Re-fetch product prices from DB to prevent tampering and compute subtotal
        $subtotal = 0.0;
        $orderLines = [];
        $priceStmt = $conn->prepare("SELECT ProductPrice FROM product WHERE ProductID = ?");
        foreach ($_SESSION['cart'] as $pid => $item) {
            $qty = max(1, (int)$item['qty']);
            $priceStmt->bind_param('s', $pid);
            $priceStmt->execute();
            $priceRes = $priceStmt->get_result();
            if ($priceRes && $priceRes->num_rows > 0) {
                $dbPrice = (float)$priceRes->fetch_assoc()['ProductPrice'];
                $orderLines[] = ['pid' => $pid, 'qty' => $qty, 'price' => $dbPrice];
                $subtotal += ($qty * $dbPrice);
            }
        }

        // Generate IDs and insert order
        $orderID = 'O-' . date('YmdHis') . '-' . rand(100, 999);
        $orderStatus = 'Pending';
        
        // Fetch a valid AdminID to satisfy FK
        $adminId = null;
        $adminRes = $conn->query("SELECT AdminID FROM admin ORDER BY AdminID ASC LIMIT 1");
        if ($adminRes && $adminRes->num_rows > 0) {
            $adminId = $adminRes->fetch_assoc()['AdminID'];
        } else {
            $feedback = 'No admin account available to record order.';
        }
        
        $insertOrder = $conn->prepare("INSERT INTO orders (OrderID, CustomerID, AdminID, OrderStatus, Subtotal) VALUES (?, ?, ?, ?, ?)");
        $insertOrder->bind_param('ssssd', $orderID, $customerID, $adminId, $orderStatus, $subtotal);

        if ($insertOrder->execute()) {
            $detailStmt = $conn->prepare("INSERT INTO order_details (OrderDetailID, OrderID, ProductID, QuantityOrdered, ProductPrice) VALUES (?, ?, ?, ?, ?)");
            $i = 1;
            foreach ($orderLines as $line) {
                $detailID = 'OD-' . date('YmdHis') . '-' . $i++ . '-' . rand(10, 99);
                $detailStmt->bind_param('sssid', $detailID, $orderID, $line['pid'], $line['qty'], $line['price']);
                $detailStmt->execute();
            }

            // Update stock after placing order, mirroring POS behavior
$stockStmt = $conn->prepare("UPDATE product SET StockOnHandPerCase = GREATEST(StockOnHandPerCase - ?, 0) WHERE ProductID = ?");
foreach ($orderLines as $line) {
    $stockStmt->bind_param('is', $line['qty'], $line['pid']);
    $stockStmt->execute();
}

            $_SESSION['cart'] = [];
            header('Location: order_confirmation.php?order_id=' . urlencode($orderID));
            exit;
        } else {
            $feedback = 'Failed to place order: ' . $conn->error;
        }
    }
}

// Fetch products for listing
$products = [];
$productQuery = "SELECT ProductID, ProductName, ProductPrice, ProductCategory, StockOnHandPerCase FROM product ORDER BY ProductCategory, ProductName";
$productResult = $conn->query($productQuery);
if ($productResult) {
    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }
}
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-store"></i> 8JJ's Trading Incorporation</h3>
        <p>Customer Panel</p>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="order.php">
                <i class="fas fa-shopping-cart"></i> Place Order
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="orders.php">
                <i class="fas fa-list-alt"></i> My Orders
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="profile.php">
                <i class="fas fa-user"></i> My Profile
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
                <h2 class="mb-0">Place an Order</h2>
                <?php if (!empty($feedback)): ?>
                    <span class="badge bg-info"><?php echo htmlspecialchars($feedback); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-7">
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Products</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="productTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['ProductName']); ?></td>
                                        <td><?php echo htmlspecialchars($p['ProductCategory']); ?></td>
                                        <td>₱<?php echo number_format($p['ProductPrice'], 2); ?></td>
                                        <td><?php echo (int)$p['StockOnHandPerCase']; ?></td>
                                        <td>
                                            <form method="post" class="d-flex align-items-center">
                                                <input type="hidden" name="action" value="add">
                                                <input type="hidden" name="product_id" value="<?php echo $p['ProductID']; ?>">
                                                <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($p['ProductName']); ?>">
                                                <input type="hidden" name="product_category" value="<?php echo htmlspecialchars($p['ProductCategory']); ?>">
                                                <input type="hidden" name="product_price" value="<?php echo $p['ProductPrice']; ?>">
                                                <input type="number" name="quantity" class="form-control form-control-sm me-2" value="1" min="1" style="width: 80px;">
                                                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>Add</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Your Cart</h5>
                        <form method="post">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>Clear</button>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php if (empty($_SESSION['cart'])): ?>
                            <p class="text-muted">Your cart is empty.</p>
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="action" value="update">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Price</th>
                                                <th>Qty</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $cartTotal = 0.0; foreach ($_SESSION['cart'] as $pid => $item): $line = $item['qty'] * $item['price']; $cartTotal += $line; ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                                <td style="width: 100px;">
                                                    <input type="number" name="quantities[<?php echo $pid; ?>]" class="form-control form-control-sm" min="0" value="<?php echo (int)$item['qty']; ?>">
                                                </td>
                                                <td>₱<?php echo number_format($line, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-sync me-1"></i>Update Cart</button>
                                    </div>
                                    <h5 class="mb-0">Subtotal: <span class="text-primary">₱<?php echo number_format($cartTotal, 2); ?></span></h5>
                                </div>
                            </form>
                            <hr>
                            <form method="post">
                                <input type="hidden" name="action" value="checkout">
                                <button type="submit" class="btn btn-success w-100"><i class="fas fa-check me-1"></i>Checkout</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('productTable');
        if (table) {
            $(table).DataTable({
                pageLength: 10,
                order: [[1, 'asc'], [0, 'asc']]
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
