<?php
/**
 * db.php
 * Database connection file for the Multi-Criteria Event Tabulation System.
 * Uses PDO with MySQL (XAMPP environment).
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'tabulation_system');
define('DB_USER', 'root');      // default XAMPP user
define('DB_PASS', '');          // default XAMPP password is empty
define('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]));
}

function getDB(): PDO {
    global $pdo;
    return $pdo;
}
