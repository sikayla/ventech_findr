<?php
// get_unread_count.php
// This file fetches the number of unread notifications for the logged-in user
// and returns it as a JSON response.

// **1. Start Session**
// Ensure this is the absolute first line, before any output (including whitespace)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set the content type to application/json
header('Content-Type: application/json');

// **2. Include Database Connection**
// Ensure this path is correct relative to get_unread_count.php
// Adjust the path as necessary if includes/db_connection.php is in a different location
require_once __DIR__ . '/includes/db_connection.php'; // Using __DIR__ for robust path inclusion

// Ensure $pdo is available from the included file
if (!isset($pdo) || !$pdo instanceof PDO) {
    // Log the error internally
    error_log("PDO connection not available in get_unread_count.php");
    // Return a JSON error response
    echo json_encode(['error' => 'Database connection not available.', 'count' => 0]);
    exit; // Stop script execution
}

// **3. Check User Authentication**
$loggedInUserId = $_SESSION['user_id'] ?? null;

if (!$loggedInUserId) {
    // If no user is logged in, return a count of 0
    echo json_encode(['count' => 0]);
    exit; // Stop script execution
}

// **4. Fetch Unread Notification Count**
$unread_notification_count = 0; // Default count

try {
    // Assuming you have a 'user_notifications' table with 'user_id' and 'is_read' columns
    // is_read is typically a boolean or tinyint (0 for false, 1 for true)
    $stmtNotifyCount = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id = :user_id AND is_read = 0"); // Assuming 0 means unread
    $stmtNotifyCount->execute([':user_id' => $loggedInUserId]);
    $unread_notification_count = $stmtNotifyCount->fetchColumn();

    // Ensure the result is an integer
    $unread_notification_count = (int) $unread_notification_count;

    // Return the count as a JSON response
    echo json_encode(['count' => $unread_notification_count]);

} catch (PDOException $e) {
    // Log the database error internally
    error_log("Error fetching unread notification count for user $loggedInUserId in get_unread_count.php: " . $e->getMessage());
    // Return a JSON error response, but still provide a count of 0 to the client
    echo json_encode(['error' => 'Failed to fetch notification count.', 'count' => 0]);
}

// No closing PHP tag needed if the file contains only PHP code,
// helps prevent accidental whitespace output.
