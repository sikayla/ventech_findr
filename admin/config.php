<?php
// config.php
// This file contains database connection details.
// IMPORTANT: Replace placeholder credentials with your actual database credentials.

$servername = "localhost"; // e.g., "localhost" or your database host
$username = "root";        // e.g., "root"
$password = "";            // e.g., "your_db_password"
$dbname = "ventech_db";    // Your database name from ventech.sql

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
