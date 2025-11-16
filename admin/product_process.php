<?php
require_once '../config/db_connect.php';

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

function generateProductId(string $productName): string {
    $name = strtolower($productName);
    $brandCode = 'X';
    if (strpos($name, 'coca-cola') !== false || strpos($name, 'coke') !== false) {
        $brandCode = 'C';
    } elseif (strpos($name, 'sprite') !== false) {
        $brandCode = 'SP';
    } elseif (strpos($name, 'royal') !== false) {
        $brandCode = 'R';
    } elseif (strpos($name, 'wilkins') !== false) {
        $brandCode = 'W';
    } elseif (strpos($name, 'san miguel') !== false || strpos($name, 'smb') !== false || strpos($name, 'beer') !== false) {
        $brandCode = 'SMB';
    }

    $sizeMl = null;
    if (preg_match('/(\d{3,4})\s*ml/i', $name, $m)) {
        $sizeMl = intval($m[1]);
    } elseif (preg_match('/\b(\d+)\s*l\b/i', $name, $m)) {
        $sizeMl = intval($m[1]) * 1000;
    }
    if ($sizeMl === null) {
        $sizeMl = 0; // Unknown size
    }

    $sizeCode = str_pad((string)$sizeMl, 4, '0', STR_PAD_LEFT);
    return "P-$brandCode$sizeCode";
}

// Removed legacy POST handler targeting non-existent `products` table to prevent conflicts with admin/products.php form.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add Product
    if (isset($_POST['add_product'])) {
        $productName = trim($_POST['productName'] ?? '');
        $productCategory = trim($_POST['productCategory'] ?? '');
        $productPrice = floatval($_POST['productPrice'] ?? 0);
        $qtyPerCase = intval($_POST['qtyPerCase'] ?? 0);
        $stockOnHandPerCase = intval($_POST['stockOnHandPerCase'] ?? 0);
        $reorderPoint = intval($_POST['reorderPoint'] ?? 0);

        if ($productName === '' || $productCategory === '' || $productPrice <= 0 || $qtyPerCase <= 0) {
            $_SESSION['error'] = 'Please provide valid product details.';
            header('Location: products.php');
            exit;
        }

        $productId = generateProductId($productName);

        $stmt = $conn->prepare(
            'INSERT INTO product (ProductID, ProductName, ProductCategory, ProductPrice, QtyPerCase, StockOnHandPerCase, ReorderPoint) VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->bind_param(
            'sssdiii',
            $productId,
            $productName,
            $productCategory,
            $productPrice,
            $qtyPerCase,
            $stockOnHandPerCase,
            $reorderPoint
        );

        if ($stmt->execute()) {
            // Initialize per-piece stock = cases * qty per case
            $perPiece = $qtyPerCase * $stockOnHandPerCase;
            $upd = $conn->prepare('UPDATE product SET StockOnHandPerPiece = ? WHERE ProductID = ?');
            $upd->bind_param('is', $perPiece, $productId);
            $upd->execute();

            $_SESSION['success'] = 'Product added successfully';
        } else {
            $_SESSION['error'] = 'Error adding product: ' . $conn->error;
        }
        header('Location: products.php');
        exit;
    }

    // Update Product
    if (isset($_POST['update_product'])) {
        $productId = trim($_POST['productId'] ?? '');
        $productName = trim($_POST['productName'] ?? '');
        $productCategory = trim($_POST['productCategory'] ?? '');
        $productPrice = floatval($_POST['productPrice'] ?? 0);
        $qtyPerCase = intval($_POST['qtyPerCase'] ?? 0);
        $stockOnHandPerCase = intval($_POST['stockOnHandPerCase'] ?? 0);
        $reorderPoint = intval($_POST['reorderPoint'] ?? 0);

        if ($productId === '' || $productName === '' || $productCategory === '' || $productPrice <= 0 || $qtyPerCase <= 0) {
            $_SESSION['error'] = 'Please provide valid product details.';
            header('Location: products.php');
            exit;
        }

        $stmt = $conn->prepare(
            'UPDATE product SET ProductName=?, ProductCategory=?, ProductPrice=?, QtyPerCase=?, StockOnHandPerCase=?, ReorderPoint=? WHERE ProductID=?'
        );
        $stmt->bind_param(
            'ssdiiis',
            $productName,
            $productCategory,
            $productPrice,
            $qtyPerCase,
            $stockOnHandPerCase,
            $reorderPoint,
            $productId
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = 'Product updated successfully';
        } else {
            $_SESSION['error'] = 'Error updating product: ' . $conn->error;
        }
        header('Location: products.php');
        exit;
    }
}

header('Location: products.php');
exit;
