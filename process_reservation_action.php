<?php
// process_reservation_action.php

// **1. Start Session** (MUST be the very first thing)
// Ensure this is the absolute first line, before any output (including whitespace)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// **2. Include Database Connection**
require_once 'includes/db_connection.php'; // Ensure this path is correct and it initializes $pdo

// Ensure $pdo is available from the included file
if (!isset($pdo)) {
    error_log("PDO connection failed in process_reservation_action.php");
    $_SESSION['message'] = "System error: Database connection failed.";
    $_SESSION['message_type'] = 'error';
    header("Location: /ventech_locator/reservation_manage.php"); // Redirect back on critical error
    exit();
}

// **3. Check for POST Request and Required Data**
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['reservation_id'], $_POST['action'], $_POST['csrf_token'])) {
    $_SESSION['message'] = "Invalid request method or missing data.";
    $_SESSION['message_type'] = 'error';
    header("Location: /ventech_locator/reservation_manage.php");
    exit();
}

// **4. Validate CSRF Token**
// This protects against Cross-Site Request Forgery
$csrf_token = $_POST['csrf_token'];
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    // Token is missing or does not match
    $_SESSION['message'] = "Security error: Invalid request token.";
    $_SESSION['message_type'] = 'error';
    // Clear the potentially compromised token
    unset($_SESSION['csrf_token']);
    header("Location: /ventech_locator/reservation_manage.php");
    exit();
}

// After successful validation, you might want to regenerate the token to prevent replay attacks,
// especially if the form remains on the page after submission (though in this case we redirect).
// unset($_SESSION['csrf_token']); // Consider regenerating in the destination page if needed

// **5. Sanitize and Validate Input**
$reservation_id = filter_var($_POST['reservation_id'], FILTER_VALIDATE_INT);
$action = strtolower(trim($_POST['action'])); // 'accept' or 'reject'

// Validate reservation_id
if ($reservation_id === false || $reservation_id <= 0) {
    $_SESSION['message'] = "Invalid reservation ID.";
    $_SESSION['message_type'] = 'error';
    header("Location: /ventech_locator/reservation_manage.php");
    exit();
}

// Validate action
$allowed_actions = ['accept', 'reject'];
if (!in_array($action, $allowed_actions)) {
    $_SESSION['message'] = "Invalid action specified.";
    $_SESSION['message_type'] = 'error';
    header("Location: /ventech_locator/reservation_manage.php");
    exit();
}

// **6. Determine New Status Based on Action**
$new_status = '';
$success_message = '';
$error_message = '';

switch ($action) {
    case 'accept':
        $new_status = 'accepted'; // Or 'confirmed' depending on your workflow
        $success_message = "Reservation request accepted.";
        $error_message = "Failed to accept reservation.";
        break;
    case 'reject':
        $new_status = 'rejected';
        $success_message = "Reservation request rejected.";
        $error_message = "Failed to reject reservation.";
        break;
    // Add other actions like 'cancel' if needed
    default:
        // This case should ideally not be reached due to the in_array check above
        $_SESSION['message'] = "Unexpected action received.";
        $_SESSION['message_type'] = 'error';
        header("Location: /ventech_locator/reservation_manage.php");
        exit();
}

// **7. Update Reservation Status in Database**
try {
    // IMPORTANT: Also check if the logged-in user *owns* the venue associated with this reservation
    // to prevent one venue owner from managing another's reservations.
    // This requires checking the venue table based on reservation_id.

    // First, get the venue_id for the reservation
    $stmtVenueCheck = $pdo->prepare("SELECT venue_id FROM venue_reservations WHERE id = :reservation_id");
    $stmtVenueCheck->execute([':reservation_id' => $reservation_id]);
    $reservation = $stmtVenueCheck->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
         $_SESSION['message'] = "Reservation not found.";
         $_SESSION['message_type'] = 'error';
         header("Location: /ventech_locator/reservation_manage.php");
         exit();
    }

    $venue_id = $reservation['venue_id'];

    // Then, check if the logged-in user owns this venue
    if (!isset($_SESSION['user_id'])) {
        // This check should ideally happen earlier, but as a safeguard:
        $_SESSION['message'] = "You must be logged in to manage reservations.";
        $_SESSION['message_type'] = 'error';
        header("Location: /ventech_locator/client/client_login.php?error=not_logged_in"); // Redirect to login
        exit;
    }
    $loggedInUserId = $_SESSION['user_id'];

    $stmtOwnershipCheck = $pdo->prepare("SELECT COUNT(*) FROM venue WHERE id = :venue_id AND user_id = :user_id");
    $stmtOwnershipCheck->execute([':venue_id' => $venue_id, ':user_id' => $loggedInUserId]);
    $is_owner = $stmtOwnershipCheck->fetchColumn();

    if ($is_owner == 0) {
        // The logged-in user does not own this venue
        $_SESSION['message'] = "Unauthorized action: You do not own the venue for this reservation.";
        $_SESSION['message_type'] = 'error';
        header("Location: /ventech_locator/reservation_manage.php");
        exit();
    }


    // If ownership is confirmed, proceed with the status update
    $stmtUpdate = $pdo->prepare("
        UPDATE venue_reservations
        SET status = :status, updated_at = NOW()
        WHERE id = :id
    ");
    $stmtUpdate->execute([
        ':status' => $new_status,
        ':id' => $reservation_id
    ]);

    // Check if any row was affected (meaning the update was successful)
    if ($stmtUpdate->rowCount() > 0) {
        $_SESSION['message'] = $success_message;
        $_SESSION['message_type'] = 'success';

        // Optional: Trigger a notification for the user who made the reservation
        // You would need to fetch the booker_user_id from the reservation here if user_id is not NULL
        /*
        if ($reservation['booker_user_id']) {
             $notification_message = "Your reservation for " . htmlspecialchars($venue_name) . " on " . htmlspecialchars($reservation['event_date']) . " has been " . htmlspecialchars($new_status) . ".";
             $stmtNotify = $pdo->prepare("INSERT INTO user_notifications (user_id, reservation_id, message, status_changed_to) VALUES (?, ?, ?, ?)");
             $stmtNotify->execute([$reservation['booker_user_id'], $reservation_id, $notification_message, $new_status]);
        }
        */

    } else {
        // This might happen if the reservation ID was valid but the status was already the target status,
        // or if something else prevented the update.
        $_SESSION['message'] = "Reservation status was already " . htmlspecialchars($new_status) . " or no changes were made.";
        $_SESSION['message_type'] = 'info'; // Or 'warning'
    }

} catch (PDOException $e) {
    error_log("Database error updating reservation status (ID: {$reservation_id}, Action: {$action}): " . $e->getMessage());
    $_SESSION['message'] = $error_message . " A database error occurred.";
    $_SESSION['message_type'] = 'error';
}

// **8. Redirect Back to the Manage Reservations Page**
header("Location: /ventech_locator/reservation_manage.php"); // Adjust path as needed
exit();

?>