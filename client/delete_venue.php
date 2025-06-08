<?php
// Start session to access user_id
session_start();

// Include database connection
require_once('../includes/db_connection.php'); // Adjust path as necessary

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../client_login.php"); // Redirect to login page
    exit;
}

$loggedInOwnerUserId = $_SESSION['user_id'];

// Check if PDO connection is available
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("PDO connection not available in delete_venue.php");
    header("Location: /ventech_locator/client_dashboard.php?delete_error=db_error");
    exit;
}

// Check if venue_id is provided and is a valid integer
if (!isset($_POST['venue_id']) || !filter_var($_POST['venue_id'], FILTER_VALIDATE_INT)) {
    header("Location: /ventech_locator/client_dashboard.php?delete_error=invalid_id");
    exit;
}

$venue_id = (int)$_POST['venue_id'];

try {
    // 1. Verify the venue belongs to the logged-in user
    $stmt = $pdo->prepare("SELECT user_id, image_path FROM venue WHERE id = ?");
    $stmt->execute([$venue_id]);
    $venue = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venue) {
        // Venue not found
        header("Location: /ventech_locator/client_dashboard.php?delete_error=invalid_id");
        exit;
    }

    if ($venue['user_id'] != $loggedInOwnerUserId) {
        // User does not own this venue
        header("Location: /ventech_locator/client_dashboard.php?delete_error=unauthorized");
        exit;
    }

    // Start a transaction for atomicity
    $pdo->beginTransaction();

    // 2. Delete related reservations first (if any)
    // This is important to maintain referential integrity if venue_reservations
    // has a foreign key constraint on venue_id with ON DELETE RESTRICT or NO ACTION.
    // If your foreign key has ON DELETE CASCADE, this step might be optional,
    // but it's good practice to be explicit.
    $stmtDeleteReservations = $pdo->prepare("DELETE FROM venue_reservations WHERE venue_id = ?");
    $stmtDeleteReservations->execute([$venue_id]);

    // 3. Delete the venue
    $stmtDeleteVenue = $pdo->prepare("DELETE FROM venue WHERE id = ?");
    $stmtDeleteVenue->execute([$venue_id]);

    // 4. Delete the associated image file from the server
    if (!empty($venue['image_path'])) {
        $image_file_path = $_SERVER['DOCUMENT_ROOT'] . '/ventech_locator/uploads/' . $venue['image_path'];
        // Ensure the path is safe and file exists before attempting to delete
        if (file_exists($image_file_path) && is_file($image_file_path)) {
            unlink($image_file_path);
        }
    }

    // Commit the transaction
    $pdo->commit();

    // Redirect with success message
    header("Location: /ventech_locator/client_dashboard.php?venue_deleted=true");
    exit;

} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    error_log("Error deleting venue ID {$venue_id} for user {$loggedInOwnerUserId}: " . $e->getMessage());
    header("Location: /ventech_locator/client_dashboard.php?delete_error=db_error");
    exit;
} catch (Exception $e) {
    // Catch any other exceptions
    $pdo->rollBack();
    error_log("General error deleting venue ID {$venue_id}: " . $e->getMessage());
    header("Location: /ventech_locator/client_dashboard.php?delete_error=db_error");
    exit;
}
?>
