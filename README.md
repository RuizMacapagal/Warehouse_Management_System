# Warehouse Management System

A modern, user-friendly warehouse management system built with HTML, PHP, and MySQL using XAMPP.

## Features

- **Role-based Authentication**: Separate interfaces for admin, cashier, inventory staff, and customers
- **Admin Dashboard**: Comprehensive reporting and system management
- **POS System**: For cashiers to process customer orders
- **Inventory Management**: Track stock levels and product information
- **Customer Ordering**: Self-service ordering portal for customers

## Setup Instructions

### Prerequisites

- XAMPP (with Apache and MySQL)
- Web browser

### Installation Steps

1. **Install XAMPP**
   - Download and install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
   - Start Apache and MySQL services from the XAMPP Control Panel

2. **Database Setup**
   - Open your web browser and navigate to `http://localhost/phpmyadmin`
   - Create a new database named `warehouse_management`
   - Import the `database.sql` file from the project root directory

3. **Project Setup**
   - Copy the entire project folder to `C:\xampp\htdocs\` (or your XAMPP installation directory)
   - Ensure the folder is named `Management_System`

4. **Configuration**
   - Open `config/db_connect.php` and verify the database connection settings:
     ```php
     $servername = "localhost";
     $username = "root";
     $password = "";
     $dbname = "warehouse_management";
     ```
   - Modify if your MySQL setup uses different credentials

5. **Access the System**
   - Open your web browser and navigate to `http://localhost/Management_System/`
   - You will be redirected to the login page

### Default Login Credentials

| Role      | Username | Password |
|-----------|----------|----------|
| Admin     | admin    | admin123 |
| Cashier   | cashier  | cash123  |
| Inventory | inventory| inv123   |
| Customer  | customer | cust123  |

## System Structure

```
Management_System/
├── admin/              # Admin interface files
├── assets/             # CSS, JS, and image files
│   ├── css/
│   ├── js/
│   └── img/
├── cashier/            # Cashier interface files
├── config/             # Configuration files
├── customer/           # Customer interface files
├── includes/           # Common include files
├── inventory/          # Inventory interface files
├── database.sql        # Database schema and sample data
├── db_connect.php      # Database connection script
├── index.php           # Main entry point
├── login.php           # Login page
├── logout.php          # Logout script
└── README.md           # This file
```

## Usage

### Admin
- Access comprehensive dashboard
- View all orders, inventory, and sales reports
- Manage users and system settings

### Cashier
- Process customer orders through POS
- View and print receipts
- Manage customer information

### Inventory Staff
- Track inventory levels
- Manage products and stock
- Process deliveries and returns

### Customer
- Browse available products
- Place orders online
- View order history and status

## Development

This system was developed using:
- PHP for server-side logic
- MySQL for database management
- HTML, CSS, and JavaScript for frontend
- Bootstrap for responsive design
- Font Awesome for icons
- DataTables for interactive tables
- Chart.js for data visualization

## License

This project is for educational purposes only.