<?php
/**
 * Database configuration using PDO.
 * Adjust credentials as needed for your XAMPP setup.
 */
return [
    'host' => 'localhost',
    'dbname' => 'aa_pharmacy',
    'username' => 'root',
    'password' => '', // XAMPP default: empty password
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];