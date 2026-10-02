<?php
/**
 * Application-wide configuration constants.
 */
return [
    'app_name' => 'AA Pharmacy System',
    'app_url' => 'http://localhost/aa_pharmacy/public',
    'timezone' => 'Asia/Manila',
    'currency' => 'PHP',
    'csrf_token_name' => '_csrf_token',
    'csrf_cookie_name' => '_csrf_cookie',
    'session_name' => 'AA_PHARMACY_SESSION',
    'low_stock_threshold' => 10, // default units
    'near_expiry_days' => 30,     // days before expiry to flag
];