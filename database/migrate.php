<?php

/**
 * Migration Runner for AA Pharmacy System
 * Creates the schema (roles, users, audit_logs) via raw PDO.
 * Run after database/create_db.php.
 */

require_once __DIR__ . '/../app/core/Database.php';

use App\Core\Database;

function runMigrations() {
    $db = Database::getInstance()->getConnection();

    echo "Starting migrations...\n";

    // 1. Create Roles Table
    echo "Creating roles table... ";
    $sqlRoles = "CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(30) NOT NULL UNIQUE,
        description VARCHAR(100) NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sqlRoles);
    echo "Done.\n";

    // 2. Create Users Table
    echo "Creating users table... ";
    $sqlUsers = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id_number VARCHAR(20) NOT NULL UNIQUE,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        role_id INT NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        last_login_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (role_id) REFERENCES roles(id),
        INDEX (username),
        INDEX (email),
        INDEX (role_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sqlUsers);
    echo "Done.\n";

    // 3. Create Audit Logs Table
    echo "Creating audit_logs table... ";
    $sqlLogs = "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(50) NOT NULL,
        table_affected VARCHAR(50) NULL,
        record_id INT NULL,
        description TEXT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (action),
        INDEX (created_at),
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sqlLogs);
    echo "Done.\n";

    // 4. Create Products Table
    echo "Creating products table... ";
    $sqlProducts = "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        generic_name VARCHAR(100) NULL,
        category VARCHAR(50) NULL,
        unit VARCHAR(20) NOT NULL DEFAULT 'pc',
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
        requires_prescription TINYINT(1) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sqlProducts);
    echo "Done.\n";

    // 5. Create Batches Table (stock tracked by batch; FEFO index on expiry_date)
    echo "Creating batches table... ";
    $sqlBatches = "CREATE TABLE IF NOT EXISTS batches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        lot_number VARCHAR(50) NOT NULL,
        expiry_date DATE NOT NULL,
        quantity INT NOT NULL DEFAULT 0,
        unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
        supplier VARCHAR(100) NULL,
        received_date DATE NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id),
        INDEX (expiry_date),
        INDEX (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sqlBatches);
    echo "Done.\n";

    // 6. Create Inventory Movements Table (rule: every stock change logs a movement)
    echo "Creating inventory_movements table... ";
    $sqlMovements = "CREATE TABLE IF NOT EXISTS inventory_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        batch_id INT NULL,
        movement_type VARCHAR(20) NOT NULL,
        quantity_change INT NOT NULL,
        unit_cost DECIMAL(10,2) NULL,
        reference VARCHAR(50) NULL,
        user_id INT NULL,
        notes VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (batch_id) REFERENCES batches(id),
        INDEX (created_at),
        INDEX (product_id),
        INDEX (batch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sqlMovements);
    echo "Done.\n";

    // Phase 4: 7. Create Suppliers Table
    echo "Creating suppliers table... ";
    $sqlSuppliers = "CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        contact_person VARCHAR(100) NULL,
        phone VARCHAR(30) NULL,
        email VARCHAR(100) NULL,
        address VARCHAR(255) NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sqlSuppliers);
    echo "Done.\n";

    // Phase 4: 8. Create Purchase Orders Table
    echo "Creating purchase_orders table... ";
    $sqlPOs = "CREATE TABLE IF NOT EXISTS purchase_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        po_number VARCHAR(30) NOT NULL UNIQUE,
        supplier_id INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'ordered',
        order_date DATE NULL,
        expected_date DATE NULL,
        notes VARCHAR(255) NULL,
        created_by INT NULL,
        received_by INT NULL,
        received_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
        FOREIGN KEY (created_by) REFERENCES users(id),
        FOREIGN KEY (received_by) REFERENCES users(id),
        INDEX (po_number),
        INDEX (supplier_id),
        INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sqlPOs);
    echo "Done.\n";

    // Phase 4: 9. Create Purchase Order Items Table
    echo "Creating purchase_order_items table... ";
    $sqlPOItems = "CREATE TABLE IF NOT EXISTS purchase_order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_order_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity_ordered INT NOT NULL DEFAULT 0,
        quantity_received INT NOT NULL DEFAULT 0,
        unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id),
        INDEX (purchase_order_id),
        INDEX (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sqlPOItems);
    echo "Done.\n";

    // Phase 5: 10. Create Sales Table (POS)
    echo "Creating sales table... ";
    $sqlSales = "CREATE TABLE IF NOT EXISTS sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_number VARCHAR(30) NOT NULL UNIQUE,
        customer_name VARCHAR(100) NULL,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
        discount DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        payment_method VARCHAR(20) NOT NULL DEFAULT 'cash',
        amount_tendered DECIMAL(10,2) NULL,
        change_due DECIMAL(10,2) NULL,
        sold_by INT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'completed',
        voided_by INT NULL,
        voided_at TIMESTAMP NULL,
        sale_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (sold_by) REFERENCES users(id),
        FOREIGN KEY (voided_by) REFERENCES users(id),
        INDEX (sale_date),
        INDEX (status),
        INDEX (sold_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sqlSales);
    echo "Done.\n";

    // Phase 5: 11. Create Sale Items Table (records the batch each line drew from)
    echo "Creating sale_items table... ";
    $sqlSaleItems = "CREATE TABLE IF NOT EXISTS sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        product_id INT NOT NULL,
        batch_id INT NULL,
        quantity INT NOT NULL,
        unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
        line_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (batch_id) REFERENCES batches(id),
        INDEX (sale_id),
        INDEX (product_id),
        INDEX (batch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sqlSaleItems);
    echo "Done.\n";

    echo "All migrations completed successfully.\n";
}

try {
    runMigrations();
} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
