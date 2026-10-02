<?php

/**
 * Database Creator for AA Pharmacy System
 */

$config = require __DIR__ . '/../config/database.php';

try {
    $dsn = "mysql:host={$config['host']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);

    echo "Creating database if not exists: {$config['dbname']}... ";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Done.\n";

} catch (PDOException $e) {
    die("Database creation failed: " . $e->getMessage() . "\n");
}
