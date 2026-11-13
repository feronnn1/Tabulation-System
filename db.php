<?php
/**
 * db.php
 * Database connection file for the Multi-Criteria Event Tabulation System.
 * Uses PDO with MySQL (XAMPP environment).
 */

// ----- Configuration -----
define('DB_HOST', 'localhost');
define('DB_NAME', 'tabulation_system');
define('DB_USER', 'root');      // default XAMPP user
define('DB_PASS', '');          // default XAMPP password is empty
define('DB_CHARSET', 'utf8mb4');

// ----- DSN (Data Source Name) -----
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// ----- PDO Options -----
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,       // throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,              // return associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                         // use real prepared statements
];

// ----- Establish Connection -----
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // In production, log this instead of exposing details to the user.
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]));
}

/**
 * Helper function: returns the active PDO connection.
 * Useful if you prefer calling a function instead of relying on the $pdo global.
 */
function getDB(): PDO {
    global $pdo;
    return $pdo;
}