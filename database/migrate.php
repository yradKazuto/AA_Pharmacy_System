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

    echo "All migrations completed successfully.\n";
}

try {
    runMigrations();
} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
