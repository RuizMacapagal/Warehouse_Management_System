<?php

require_once '../config/db_connect.php';
include '../includes/header.php';

// Check if user role is customer
if ($_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit;
}

// Get customer information
$customerID = $_SESSION['user_id'];
$customer = null;
$query = "SELECT * FROM customer WHERE CustomerID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $customerID);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $customer = $result->fetch_assoc();
}

// Process profile update
$updateMessage = '';
$updateSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);
    
    // Validate inputs
    $errors = [];
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    if (empty($address)) {
        $errors[] = "Address is required";
    }
    
    if (empty($errors)) {
        // Update customer information
        $query = "UPDATE customer SET CustomerName = ?, CustomerNumber = ?, CustomerAddress = ?, CustomerEmail = ? WHERE CustomerID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssss", $name, $phone, $address, $email, $customerID);
        
        if ($stmt->execute()) {
            $updateSuccess = true;
            $updateMessage = "Profile updated successfully!";
            
            // Refresh customer data
            $query = "SELECT * FROM customer WHERE CustomerID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $customerID);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $customer = $result->fetch_assoc();
            }
        } else {
            $updateMessage = "Error updating profile: " . $conn->error;
        }
    } else {
        $updateMessage = "Please correct the following errors: " . implode(", ", $errors);
    }
}

// Process password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate inputs
    $errors = [];
    if (empty($currentPassword)) {
        $errors[] = "Current password is required";
    }
    if (empty($newPassword)) {
        $errors[] = "New password is required";
    } elseif (strlen($newPassword) < 6) {
        $errors[] = "New password must be at least 6 characters";
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = "New passwords do not match";
    }
    
    if (empty($errors)) {
        // Verify current password
        $query = "SELECT Password FROM users WHERE UserID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $customerID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($currentPassword, $user['Password'])) {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $query = "UPDATE users SET Password = ? WHERE UserID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $hashedPassword, $customerID);
                
                if ($stmt->execute()) {
                    $updateSuccess = true;
                    $updateMessage = "Password changed successfully!";
                } else {
                    $updateMessage = "Error changing password: " . $conn->error;
                }
            } else {
                $updateMessage = "Current password is incorrect";
            }
        } else {
            $updateMessage = "Error retrieving user information";
        }
    } else {
        $updateMessage = "Please correct the following errors: " . implode(", ", $errors);
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
            <a class="nav-link" href="order.php">
                <i class="fas fa-shopping-cart"></i> Place Order
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="orders.php">
                <i class="fas fa-list-alt"></i> My Orders
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="profile.php">
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
            <div class="col-md-12">
                <h2 class="mb-0">My Profile</h2>
                <p class="text-muted">Manage your account information and password</p>
            </div>
        </div>

        <?php if (!empty($updateMessage)): ?>
        <div class="alert alert-<?php echo $updateSuccess ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $updateMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="customerID" class="form-label">Customer ID</label>
                                <input type="text" class="form-control" id="customerID" value="<?php echo $customer['CustomerID']; ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo $customer['CustomerName']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo $customer['CustomerNumber']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo $customer['CustomerAddress']; ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $customer['CustomerEmail'] ?? ''; ?>">
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text">Password must be at least 6 characters long.</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key me-1"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Account Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-muted">Last Login</label>
                            <p class="mb-0">
                                <i class="fas fa-clock me-1"></i>
                                <?php echo date('F d, Y h:i A'); ?>
                            </p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Account Created</label>
                            <p class="mb-0">
                                <i class="fas fa-calendar-alt me-1"></i>
                                <?php echo date('F d, Y', strtotime('-30 days')); ?>
                            </p>
                        </div>
                        <div>
                            <label class="form-label text-muted">Account Status</label>
                            <p class="mb-0">
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Active</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
