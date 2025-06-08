<?php
// db_connection.php

$host = 'localhost';
$db   = 'ventech_db';
$user = 'root';
$pass = ''; // Add your actual password here
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Better error handling
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Return rows as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                   // Use native prepares if supported
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Optional: log error to a file
    error_log('Database connection error: ' . $e->getMessage(), 3, __DIR__ . '/db_errors.log');
    
    // User-friendly message
    die("We're experiencing technical issues. Please try again later.");
}
?>
