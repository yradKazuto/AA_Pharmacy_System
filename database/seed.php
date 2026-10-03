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

    seedSuppliers($db);
    seedInventory($db);

    echo "Seeding completed successfully.\n";
}

/**
 * Seed sample suppliers (idempotent).
 */
function seedSuppliers($db) {
    $exists = $db->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
    if ($exists > 0) {
        echo "Suppliers already seeded. Skipped.\n";
        return;
    }

    echo "Seeding suppliers... ";
    $stmt = $db->prepare(
        "INSERT INTO suppliers (name, contact_person, phone, email, address)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute(['MedSupply Co.', 'R. Santos', '+63 917 555 0101', 'sales@medsupply.ph', 'Zamboanga City']);
    $stmt->execute(['PharmaDist Inc.', 'L. Fernandez', '+63 918 555 0202', 'orders@pharmadist.ph', 'Manila']);
    $stmt->execute(['GlobalCare Ltd.', 'J. Reyes', '+63 919 555 0303', 'contact@globalcare.ph', 'Cebu City']);
    echo "Done.\n";
}

/**
 * Seed sample products, batches, and inventory movements (idempotent).
 * Includes FEFO-targeted fixtures: one product with multiple batches at
 * different expiry dates (one EXPIRED, one near-expiry), plus a low-stock
 * product. Every received batch logs an inventory_movements row (rule 4).
 */
function seedInventory($db) {
    // Check if products were already seeded
    $exists = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    if ($exists > 0) {
        echo "Products already seeded. Skipped.\n";
        return;
    }

    echo "Seeding products and batches... ";

    $products = [
        // name, generic_name, category, unit, unit_price, requires_prescription
        ['Paracetamol 500mg', 'Paracetamol', 'Tablet', 'tablet', 5.00, 0],
        ['Amoxicillin 500mg', 'Amoxicillin', 'Antibiotic', 'capsule', 35.00, 1],
        ['Cetirizine 10mg', 'Cetirizine', 'Antihistamine', 'tablet', 8.00, 0],
        ['Oral Rehydration Salts', 'ORS', 'Oral Solution', 'sachet', 15.00, 0],
        ['Salbutamol Inhaler', 'Salbutamol', 'Respiratory', 'inhaler', 250.00, 1],
    ];

    // INSERT products, keep product_id -> name map for batch linking
    $productStmt = $db->prepare(
        "INSERT INTO products (name, generic_name, category, unit, unit_price, requires_prescription)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $productIds = [];
    foreach ($products as $p) {
        $productStmt->execute($p);
        $productIds[$p[0]] = $db->lastInsertId();
    }

    // sample batches per product:
    // product => [ [lot, expiry, qty, cost, supplier], ... ]
    // Paracetamol gets multiple batches (FEFO test) incl. one EXPIRED and one near-expiry.
    $today = date('Y-m-d');
    $in30 = date('Y-m-d', strtotime('+30 days'));
    $far  = date('Y-m-d', strtotime('+180 days'));
    $expired = date('Y-m-d', strtotime('-10 days'));

    $batches = [
        'Paracetamol 500mg' => [
            // expired batch (never sellable)
            ['EXP-0001', $expired, 50, 3.00, 'MedSupply Co.'],
            // near-expiry batch (still sellable today, flagged by alerts)
            ['LOT-0002', $in30, 100, 3.20, 'MedSupply Co.'],
            // far-future batch (FEFO should prefer LOT-0002 first, not this one)
            ['LOT-0003', $far, 200, 3.40, 'PharmaDist Inc.'],
        ],
        'Amoxicillin 500mg' => [
            ['AMX-0001', $far, 120, 22.00, 'PharmaDist Inc.'],
        ],
        'Cetirizine 10mg' => [
            ['CET-0001', $far, 8, 4.50, 'MedSupply Co.'], // LOW STOCK (<=10)
        ],
        'Oral Rehydration Salts' => [
            ['ORS-0001', $far, 300, 8.00, 'GlobalCare Ltd.'],
        ],
        'Salbutamol Inhaler' => [
            ['SAL-0001', $far, 40, 160.00, 'PharmaDist Inc.'],
        ],
    ];

    $batchStmt = $db->prepare(
        "INSERT INTO batches (product_id, lot_number, expiry_date, quantity, unit_cost, supplier, received_date)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $movementStmt = $db->prepare(
        "INSERT INTO inventory_movements (product_id, batch_id, movement_type, quantity_change, unit_cost, notes)
         VALUES (?, ?, 'receive', ?, ?, ?)"
    );

    foreach ($batches as $productName => $rows) {
        $pid = $productIds[$productName];
        foreach ($rows as $b) {
            $batchStmt->execute([$pid, $b[0], $b[1], $b[2], $b[3], $b[4], $today]);
            $batchId = $db->lastInsertId();
            // Rule 4: every stock change creates a movement row
            $movementStmt->execute([$pid, $batchId, $b[2], $b[3], "Initial stock for lot {$b[0]}"]);
        }
    }

    echo "Done.\n";
}

try {
    runSeeders();
} catch (Exception $e) {
    die("Seeding failed: " . $e->getMessage() . "\n");
}
