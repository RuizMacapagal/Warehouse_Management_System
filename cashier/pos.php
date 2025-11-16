<?php
require_once '../config/db_connect.php';
include '../includes/header.php';

// Allow cashier and admin to access POS
if ($_SESSION['role'] != 'cashier' && $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Fetch products for POS listing
$products = [];
$productQuery = "SELECT ProductID, ProductName, ProductPrice, ProductCategory, StockOnHandPerCase, QtyPerCase FROM product ORDER BY ProductCategory, ProductName";
$productResult = $conn->query($productQuery);
if ($productResult) {
    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }
}

// Fetch customers for selection
$customers = [];
$customerQuery = "SELECT CustomerID, CustomerName FROM customer ORDER BY CustomerName";
$customerResult = $conn->query($customerQuery);
if ($customerResult) {
    while ($row = $customerResult->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Handle checkout submission
$checkoutError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $selectedCustomer = isset($_POST['customer_id']) ? trim($_POST['customer_id']) : '';
    $items = isset($_POST['items']) ? $_POST['items'] : [];
    $quantities = isset($_POST['quantities']) ? $_POST['quantities'] : [];
    $prices = isset($_POST['prices']) ? $_POST['prices'] : [];

    if (!$selectedCustomer) {
        $checkoutError = 'Please select a customer.';
    } elseif (empty($items)) {
        $checkoutError = 'Cart is empty.';
    } else {
        // Compute subtotal
        $subtotal = 0.0;
        for ($i = 0; $i < count($items); $i++) {
            $q = (int)$quantities[$i];
            $p = (float)$prices[$i];
            $subtotal += ($q * $p);
        }

        // Generate IDs
        $orderID = 'O-' . date('YmdHis') . '-' . rand(100, 999);
        $orderStatus = 'Paid';

        // Fetch a valid AdminID to satisfy FK
        $adminId = null;
        $adminRes = $conn->query("SELECT AdminID FROM admin ORDER BY AdminID ASC LIMIT 1");
        if ($adminRes && $adminRes->num_rows > 0) {
            $adminId = $adminRes->fetch_assoc()['AdminID'];
        } else {
            $checkoutError = 'No admin account available to record order.';
        }

        // Insert order with AdminID
        $insertOrder = $conn->prepare("INSERT INTO orders (OrderID, CustomerID, AdminID, OrderStatus, Subtotal) VALUES (?, ?, ?, ?, ?)");
        $insertOrder->bind_param('ssssd', $orderID, $selectedCustomer, $adminId, $orderStatus, $subtotal);

        if ($insertOrder->execute()) {
            // Insert order details and update stock per piece
            $detailStmt = $conn->prepare("INSERT INTO order_details (OrderDetailID, OrderID, ProductID, QuantityOrdered, ProductPrice) VALUES (?, ?, ?, ?, ?)");
            $stockStmt = $conn->prepare("UPDATE product SET StockOnHandPerCase = GREATEST(StockOnHandPerCase - ?, 0) WHERE ProductID = ?");

            for ($i = 0; $i < count($items); $i++) {
                $detailID = 'OD-' . date('YmdHis') . '-' . ($i + 1) . '-' . rand(10, 99);
                $pid = $items[$i];
                $qty = (int)$quantities[$i];
                $price = (float)$prices[$i];

                $detailStmt->bind_param('sssid', $detailID, $orderID, $pid, $qty, $price);
                $detailStmt->execute();

                $stockStmt->bind_param('is', $qty, $pid);
                $stockStmt->execute();
            }

            // Redirect to receipt
            header('Location: receipt.php?order_id=' . urlencode($orderID));
            exit;
        } else {
            $checkoutError = 'Failed to create order: ' . $conn->error;
        }
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
            <a class="nav-link active" href="pos.php">
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
            <a class="nav-link" href="reports.php">
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
            <div class="col-md-12 d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Point of Sale</h2>
                <div>
                    <a href="pos.php" class="btn btn-outline-secondary"><i class="fas fa-rotate"></i> Reset</a>
                </div>
            </div>
        </div>

        <?php if (!empty($checkoutError)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($checkoutError); ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Product List -->
            <div class="col-md-7 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Products</h5>
                        <input type="text" class="form-control" id="productSearch" placeholder="Search products..." style="max-width: 250px;">
                    </div>
                    <div class="card-body">
                        <?php if (empty($products)): ?>
                            <p class="text-muted">No products available.</p>
                        <?php else: ?>
                            <div class="row" id="productGrid">
                                <?php foreach ($products as $p): ?>
                                    <div class="col-md-4 mb-3 product-card" data-name="<?php echo strtolower($p['ProductName']); ?>" data-category="<?php echo strtolower($p['ProductCategory']); ?>">
                                        <div class="card h-100">
                                            <div class="card-body d-flex flex-column">
                                                <h6 class="card-title mb-1"><?php echo htmlspecialchars($p['ProductName']); ?></h6>
                                                <small class="text-muted mb-2"><?php echo htmlspecialchars($p['ProductCategory']); ?></small>
                                                <div class="mt-auto d-flex justify-content-between align-items-center">
                                                    <span class="fw-semibold">₱<?php echo number_format($p['ProductPrice'], 2); ?></span>
                                                    <button 
                                                        class="btn btn-sm btn-primary add-to-cart" 
                                                        data-id="<?php echo $p['ProductID']; ?>" 
                                                        data-name="<?php echo htmlspecialchars($p['ProductName']); ?>" 
                                                        data-price="<?php echo $p['ProductPrice']; ?>">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                                <?php $stockCases = (int)$p['StockOnHandPerCase']; ?>
                                                <small class="text-muted mt-2">Stock: <?php echo number_format($stockCases); ?> cases</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Cart & Checkout -->
            <div class="col-md-5 mb-4">
                <form method="POST" id="checkoutForm" class="card dashboard-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Cart</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Customer</label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">Select customer...</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?php echo $c['CustomerID']; ?>"><?php echo htmlspecialchars($c['CustomerName']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped" id="cartTable">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end" style="width: 90px;">Price</th>
                                        <th class="text-center" style="width: 100px;">Qty</th>
                                        <th class="text-end" style="width: 110px;">Total</th>
                                        <th style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-end">Subtotal</th>
                                        <th class="text-end" id="subtotal">₱0.00</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <input type="hidden" name="checkout" value="1">
                        <div id="hiddenInputs"></div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success" id="checkoutBtn" disabled>
                                <i class="fas fa-cash-register"></i> Checkout
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const productSearch = document.getElementById('productSearch');
    const productCards = Array.from(document.querySelectorAll('.product-card'));
    const cartTableBody = document.querySelector('#cartTable tbody');
    const subtotalEl = document.getElementById('subtotal');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const hiddenInputs = document.getElementById('hiddenInputs');

    let cart = [];

    function renderCart() {
        cartTableBody.innerHTML = '';
        let subtotal = 0;

        cart.forEach((item, index) => {
            const total = item.price * item.qty;
            subtotal += total;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.name}</td>
                <td class="text-end">₱${item.price.toFixed(2)}</td>
                <td class="text-center">
                    <input type="number" min="1" value="${item.qty}" class="form-control form-control-sm qty-input" data-index="${index}" style="width:80px;">
                </td>
                <td class="text-end">₱${total.toFixed(2)}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-danger remove-item" data-index="${index}"><i class="fas fa-trash"></i></button>
                </td>
            `;
            cartTableBody.appendChild(tr);
        });

        subtotalEl.textContent = `₱${subtotal.toFixed(2)}`;
        checkoutBtn.disabled = cart.length === 0;

        // Build hidden inputs
        hiddenInputs.innerHTML = '';
        cart.forEach(item => {
            hiddenInputs.insertAdjacentHTML('beforeend', `
                <input type="hidden" name="items[]" value="${item.id}">
                <input type="hidden" name="quantities[]" value="${item.qty}">
                <input type="hidden" name="prices[]" value="${item.price}">
            `);
        });
    }

    // Add to cart buttons
    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const name = btn.dataset.name;
            const price = parseFloat(btn.dataset.price);
            const existing = cart.find(i => i.id === id);
            if (existing) {
                existing.qty += 1;
            } else {
                cart.push({ id, name, price, qty: 1 });
            }
            renderCart();
        });
    });

    // Quantity change and remove
    cartTableBody.addEventListener('input', (e) => {
        if (e.target.classList.contains('qty-input')) {
            const idx = parseInt(e.target.dataset.index, 10);
            const val = parseInt(e.target.value, 10);
            cart[idx].qty = Math.max(1, val || 1);
            renderCart();
        }
    });
    cartTableBody.addEventListener('click', (e) => {
        if (e.target.closest('.remove-item')) {
            const idx = parseInt(e.target.closest('.remove-item').dataset.index, 10);
            cart.splice(idx, 1);
            renderCart();
        }
    });

    // Search filter
    productSearch.addEventListener('input', () => {
        const q = productSearch.value.toLowerCase();
        productCards.forEach(card => {
            const match = card.dataset.name.includes(q) || card.dataset.category.includes(q);
            card.style.display = match ? '' : 'none';
        });
    });
})();
</script>

<?php include '../includes/footer.php'; ?>
