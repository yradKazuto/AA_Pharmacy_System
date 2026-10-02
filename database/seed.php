<?php

/**
 * Seeder Runner for AA Pharmacy System
 * This script populates the database with initial data.
 */

require_once __DIR__ . '/../app/core/Database.php';

use App\Core\Database;

function runSeeders() {
    $db = Database::getInstance()->getConnection();

    echo "Starting seeding...\n";

    // 1. Seed Roles
    echo "Seeding roles... ";
    $roles = [
        ['admin', 'Full system access: users, roles, inventory, purchasing, POS, reports, online orders'],
        ['pharmacist', 'Can manage inventory, purchasing, POS, and approve online orders requiring professional review'],
        ['cashier', 'Can process POS sales and view products/inventory'],
        ['customer', 'Online ordering portal access only']
    ];

    $stmt = $db->prepare("INSERT IGNORE INTO roles (name, description) VALUES (?, ?)");
    foreach ($roles as $role) {
        $stmt->execute($role);
    }
    echo "Done.\n";

    // 2. Seed Admin User
    echo "Seeding admin user... ";
    // Check if admin exists
    $check = $db->query("SELECT id FROM users WHERE username = 'admin'")->fetch();
    if (!$check) {
        // Get admin role ID
        $role = $db->query("SELECT id FROM roles WHERE name = 'admin'")->fetch();
        $roleId = $role['id'];

        $sqlUser = "INSERT INTO users (company_id_number, username, email, password_hash, first_name, last_name, role_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sqlUser);
        $stmt->execute([
            'ADMIN-0001',
            'admin',
            'admin@aa-pharmacy.local',
            password_hash('Admin@1234', PASSWORD_DEFAULT),
            'System',
            'Administrator',
            $roleId
        ]);
        echo "Done.\n";
    } else {
        echo "Admin already exists. Skipped.\n";
    }

    echo "Seeding completed successfully.\n";
}

try {
    runSeeders();
} catch (Exception $e) {
    die("Seeding failed: " . $e->getMessage() . "\n");
}
