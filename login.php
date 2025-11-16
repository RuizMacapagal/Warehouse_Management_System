<?php
session_start();
require_once 'config/db_connect.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Simple validation
    if (empty($username) || empty($password)) {
        $error = "Username and password are required";
    } else {
        // Lookup user in users table using correct schema fields
        $sql = "SELECT id, username, password, role, email FROM users WHERE username = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            // Verify hashed password against user input (remove demo bypass)
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Update last login time
                $updateSql = "UPDATE users SET created_at = NOW() WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("i", $user['id']);
                $updateStmt->execute();
                
                // If customer, ensure mapping into customer table
                if ($user['role'] === 'customer') {
                    $checkCustomer = $conn->prepare("SELECT CustomerID FROM customer WHERE CustomerID = ?");
                    $checkCustomer->bind_param("s", $user['id']);
                    $checkCustomer->execute();
                    $custResult = $checkCustomer->get_result();
                    if (!$custResult || $custResult->num_rows === 0) {
                        $insertCustomer = $conn->prepare("INSERT INTO customer (CustomerID, CustomerName, CustomerNumber, CustomerAddress, CustomerEmail) VALUES (?, ?, ?, ?, ?)");
                        $defaultNumber = '000-000-0000';
                        $defaultAddress = 'N/A';
                        $email = !empty($user['email']) ? $user['email'] : ($user['username'] . '@example.com');
                        $insertCustomer->bind_param("sssss", $user['id'], $user['username'], $defaultNumber, $defaultAddress, $email);
                        $insertCustomer->execute();
                    }
                }
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header("Location: admin/dashboard.php");
                        break;
                    case 'cashier':
                        header("Location: cashier/pos.php");
                        break;
                    case 'inventory':
                        header("Location: inventory/dashboard.php");
                        break;
                    case 'customer':
                        header("Location: customer/dashboard.php");
                        break;
                    default:
                        header("Location: index.php");
                }
                exit;
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "User not found";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>8JJ's Trading Incorporation - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
        }
        .login-center { /* centers within full-width container */
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 100vh;
        }
        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
            overflow: hidden;
            max-width: 420px;
            width: 100%;
            padding: 24px;
            border: 1px solid rgba(0,0,0,0.08);
        }
        .btn-primary {
            background-color: #e61d2f; /* Coca-Cola red */
            border-color: #e61d2f;
            padding: 10px;
            font-weight: 600;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background-color: #c41a29;
            border-color: #c41a29;
        }
        .form-control {
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        /* Simplify to a single-column square card */
        .login-row { display: block; }
        .login-image { display: none; }
        .login-form { padding: 0; }
        .login-logo h2 { font-weight: 600; }
        .login-logo p { margin-top: -8px; }
        .form-floating label i { margin-right: 8px; }
        .btn-login { height: 48px; font-weight: 500; }
        .role-selector { text-align: center; }
        .role-btn {
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            margin: 5px;
            border: 1px solid rgba(0,0,0,0.1);
            transition: all 0.2s;
        }
        .role-btn:hover { transform: translateY(-1px); }
        .admin-btn { background-color: #dc3545; color: white; }
        .cashier-btn { background-color: #198754; color: white; }
        .inventory-btn { background-color: #fd7e14; color: white; }
        .customer-btn { background-color: #6610f2; color: white; }
    </style>
</head>
<body>
    <div class="container login-center">
        <div class="login-container">
            <div class="login-row">
                <div class="login-form">
                    <div class="login-logo text-center mb-3">
                        <h2><i class="fas fa-warehouse"></i> 8JJ's Trading Incorporation</h2>
                        <p class="text-muted">Warehouse Management System</p>
                    </div>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                            <label for="username"><i class="fas fa-user"></i> Username</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                            <label for="password"><i class="fas fa-lock"></i> Password</label>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-login">Sign In</button>
                        </div>
                    </form>
                    

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>