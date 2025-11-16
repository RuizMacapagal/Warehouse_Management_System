-- Database creation script for Warehouse Management System

CREATE DATABASE IF NOT EXISTS warehouse_management;
USE warehouse_management;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'cashier', 'inventory', 'customer') NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default users
INSERT INTO users (username, password, role, email) VALUES
('admin', '$2y$10$8WxhXQQBCIrSVttLJ9Jkl.3sCl1te4tW3VtmQPM7vLiXbYh3PIzHC', 'admin', 'admin@example.com'),
('cashier', '$2y$10$8WxhXQQBCIrSVttLJ9Jkl.3sCl1te4tW3VtmQPM7vLiXbYh3PIzHC', 'cashier', 'cashier@example.com'),
('inventory', '$2y$10$8WxhXQQBCIrSVttLJ9Jkl.3sCl1te4tW3VtmQPM7vLiXbYh3PIzHC', 'inventory', 'inventory@example.com'),
('customer', '$2y$10$8WxhXQQBCIrSVttLJ9Jkl.3sCl1te4tW3VtmQPM7vLiXbYh3PIzHC', 'customer', 'customer@example.com');

-- Product Table
CREATE TABLE IF NOT EXISTS product (
    ProductID VARCHAR(50) PRIMARY KEY,
    ProductName VARCHAR(50) NOT NULL,
    ProductPrice DECIMAL(10,2) NOT NULL,
    ProductCategory VARCHAR(50) NOT NULL,
    ProductStatus VARCHAR(50) DEFAULT 'Available',
    StockOnHandPerCase INT DEFAULT 0,
    QtyPerCase INT DEFAULT 0,
    MinimumStockLevel INT DEFAULT 0,
    MaximumStockLevel INT DEFAULT 500,
    ReorderPoint INT DEFAULT 0,
    StockOnHandPerPiece INT DEFAULT 0,
    LastUpdated DATETIME DEFAULT CURRENT_TIMESTAMP,
    DeliveryFSID VARCHAR(50) NULL
);

-- Customer Table
CREATE TABLE IF NOT EXISTS customer (
    CustomerID VARCHAR(50) PRIMARY KEY,
    CustomerName VARCHAR(50) NOT NULL,
    CustomerNumber VARCHAR(20) NOT NULL,
    CustomerAddress VARCHAR(500) NOT NULL,
    CustomerEmail VARCHAR(500) NOT NULL
);

-- Admin Table
CREATE TABLE IF NOT EXISTS admin (
    AdminID VARCHAR(50) PRIMARY KEY,
    AdminName VARCHAR(50) NOT NULL,
    Email VARCHAR(50) NOT NULL,
    Role VARCHAR(50) NOT NULL,
    Password VARCHAR(255) NOT NULL
);

-- Order Table
CREATE TABLE IF NOT EXISTS orders (
    OrderID VARCHAR(50) PRIMARY KEY,
    CustomerID VARCHAR(50) NOT NULL,
    AdminID VARCHAR(50) NOT NULL,
    OrderDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    OrderStatus VARCHAR(50) DEFAULT 'Pending',
    Subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (CustomerID) REFERENCES customer(CustomerID),
    FOREIGN KEY (AdminID) REFERENCES admin(AdminID)
);

-- Order Details Table
CREATE TABLE IF NOT EXISTS order_details (
    OrderDetailID VARCHAR(50) PRIMARY KEY,
    OrderID VARCHAR(50) NOT NULL,
    ProductID VARCHAR(50) NOT NULL,
    QuantityOrdered INT NOT NULL,
    ProductPrice DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (OrderID) REFERENCES orders(OrderID),
    FOREIGN KEY (ProductID) REFERENCES product(ProductID)
);

-- Sales Table
CREATE TABLE IF NOT EXISTS sales (
    SalesID VARCHAR(50) PRIMARY KEY,
    OrderID VARCHAR(50) NOT NULL,
    AdminID VARCHAR(50) NOT NULL,
    SaleDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    Subtotal DECIMAL(10,2) NOT NULL,
    PaymentStatus VARCHAR(50) DEFAULT 'Unpaid',
    FOREIGN KEY (OrderID) REFERENCES orders(OrderID),
    FOREIGN KEY (AdminID) REFERENCES admin(AdminID)
);

-- Reports Table
CREATE TABLE IF NOT EXISTS reports (
    ReportID VARCHAR(50) PRIMARY KEY,
    ReportName VARCHAR(50) NOT NULL,
    CreateDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    Description VARCHAR(50) NOT NULL,
    DataSource VARCHAR(50) NOT NULL
);

-- Returns from Customer Table
CREATE TABLE IF NOT EXISTS returns_from_customer (
    ReturnFCID VARCHAR(50) PRIMARY KEY,
    CustomerID VARCHAR(50) NOT NULL,
    ProductID VARCHAR(50) NOT NULL,
    OrderID VARCHAR(50) NOT NULL,
    NumberOfItemsReturned INT NOT NULL,
    NumberOfItemsChanged INT NOT NULL,
    Date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CustomerID) REFERENCES customer(CustomerID),
    FOREIGN KEY (ProductID) REFERENCES product(ProductID),
    FOREIGN KEY (OrderID) REFERENCES orders(OrderID)
);

-- Delivery From Supplier Table
CREATE TABLE IF NOT EXISTS delivery_from_supplier (
    DeliveryFSID VARCHAR(50) PRIMARY KEY,
    TransactionID VARCHAR(50) NOT NULL,
    OrderToSupplierCostRelatedID VARCHAR(50) NOT NULL,
    OrderToSupplierSendingID VARCHAR(50) NOT NULL,
    Date DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Order To Supplier Table
CREATE TABLE IF NOT EXISTS order_to_supplier (
    TransactionID VARCHAR(50) PRIMARY KEY,
    ProductID VARCHAR(50) NOT NULL,
    SupplierID VARCHAR(50) NOT NULL,
    NumberOfOrderedItems INT NOT NULL,
    Date DATETIME DEFAULT CURRENT_TIMESTAMP,
    Remarks VARCHAR(50) DEFAULT 'Completed',
    FOREIGN KEY (ProductID) REFERENCES product(ProductID),
    FOREIGN KEY (SupplierID) REFERENCES supplier(SupplierID)
);

-- Supplier Table
CREATE TABLE IF NOT EXISTS supplier (
    SupplierID VARCHAR(50) PRIMARY KEY,
    CompanyName VARCHAR(50) NOT NULL,
    ContactNumber VARCHAR(20) NOT NULL
);

-- Return To Supplier Table
CREATE TABLE IF NOT EXISTS return_to_supplier (
    SupplierID VARCHAR(50) NOT NULL,
    ProductID VARCHAR(50) NOT NULL,
    TransactionID VARCHAR(50) NOT NULL,
    NumberOfItemsReturned INT NOT NULL,
    NumberOfItemsChanged INT NOT NULL,
    Date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (SupplierID) REFERENCES supplier(SupplierID),
    FOREIGN KEY (ProductID) REFERENCES product(ProductID),
    FOREIGN KEY (TransactionID) REFERENCES order_to_supplier(TransactionID)
);

-- Users Table for Authentication
CREATE TABLE IF NOT EXISTS users (
    UserID VARCHAR(50) PRIMARY KEY,
    Username VARCHAR(50) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    Role ENUM('admin', 'cashier', 'inventory', 'customer') NOT NULL,
    Email VARCHAR(100) NOT NULL,
    LastLogin DATETIME DEFAULT NULL
);

-- Insert default admin user
INSERT INTO admin (AdminID, AdminName, Email, Role, Password) 
VALUES ('A-00001', 'Admin User', 'admin@example.com', 'Manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert default user for login
INSERT INTO users (UserID, Username, Password, Role, Email)
VALUES 
('U-00001', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'admin@example.com'),
('U-00002', 'cashier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', 'cashier@example.com'),
('U-00003', 'inventory', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'inventory', 'inventory@example.com'),
('U-00004', 'customer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 'customer@example.com');

-- Sample data for products
INSERT INTO product (ProductID, ProductName, ProductPrice, ProductCategory, StockOnHandPerCase, QtyPerCase)
VALUES 
('P-00001', 'Coke', 100.00, 'Soda', 180, 12),
('P-00002', 'Pepsi', 95.00, 'Soda', 150, 12),
('P-00003', 'Water', 50.00, 'Water', 200, 24);

-- Sample data for suppliers
INSERT INTO supplier (SupplierID, CompanyName, ContactNumber)
VALUES 
('Sup-00001', 'Coca Cola, San Miguel', '9052562376'),
('Sup-00002', 'Pepsi Co.', '9052562377'),
('Sup-00003', 'Water Inc.', '9052562378');