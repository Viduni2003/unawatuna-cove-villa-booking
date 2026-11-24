<?php
/**
 * config/db.php
 * Database connection using PDO (prepared statements, exceptions enabled).
 *
 * Default XAMPP/WAMP credentials are used. Change them if your MySQL
 * server uses a different user or password.
 */

declare(strict_types=1);

const DB_HOST    = 'localhost';
const DB_NAME    = 'villa_booking';
const DB_USER    = 'root';
const DB_PASS    = '';          // XAMPP default is an empty password
const DB_CHARSET = 'utf8mb4';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // return associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                    // use real prepared statements
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Log the real error for the developer, but never show details to visitors.
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Sorry, the system is temporarily unavailable. Please try again later.');
}
