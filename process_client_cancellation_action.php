<?php
// Start the session to access client user data
session_start();

// Include the database connection file
// Ensure this path is correct relative to process_client_cancellation_action.php
// Assuming includes folder is one level up from the directory containing this file (e.g., client/)
require_once 'includes/db_connection.php'; // Adjust path if needed

// Define the URL to redirect back to on completion or error
// This should likely be the client's main reservation management page
$redirectUrl = 'reservation_manage.php'; // Adjust path if needed

// Check if the client is logged in
if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect to the client login page
    header('Location: client_login.php'); // Adjust path if needed
    exit; // Stop script execution
}

// Get the logged-in client's user ID
$loggedInClientId = $_SESSION['user_id'];

// Check if the request method is POST and necessary data is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['reservation_id']) || !isset($_POST['action'])) {
    // If not a valid POST request, redirect with an error
    header('Location: ' . $redirectUrl . '?action_error=invalid_request');
    exit;
}

// Get and sanitize the submitted data
$reservationId = filter_var($_POST['reservation_id'], FILTER_SANITIZE_NUMBER_INT);
$action = filter_var($_POST['action'], FILTER_SANITIZE_STRING); // 'approve' or 'reject'

// Validate the submitted data
if (!$reservationId || $reservationId <= 0 || !in_array($action, ['approve', 'reject'])) {
     header('Location: ' . $redirectUrl . '?action_error=invalid_data');
     exit;
}

try {
    // Ensure PDO connection is available
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception("Database connection not available.");
    }

    // --- IMPORTANT VALIDATION ---
    // Fetch the reservation to verify:
    // 1. It exists.
    // 2. It is for a venue owned by the logged-in client.
    // 3. Its current status is 'cancellation_requested'.
    // We also fetch the user_id to notify the user later.
    $stmt = $pdo->prepare("
        SELECT
            r.id, r.user_id, r.status, r.venue_id,
            v.user_id as venue_owner_id -- Fetch the owner ID of the venue
        FROM venue_reservations r
        JOIN venue v ON r.venue_id = v.id
        WHERE r.id = :reservation_id AND v.user_id = :client_user_id AND r.status = 'cancellation_requested'
        LIMIT 1
    ");
    $stmt->execute([
        ':reservation_id' => $reservationId,
        ':client_user_id' => $loggedInClientId
    ]);
    $reservation = $stmt->fetch();

    // Check if the reservation was found and is a pending cancellation request for this client's venue
    if (!$reservation) {
        // If not found or status isn't 'cancellation_requested' for this client's venue, redirect with error
        header('Location: ' . $redirectUrl . '?action_error=not_found_or_status');
        exit;
    }

    // Determine the new status based on the client's action
    $newStatus = ($action === 'approve') ? 'cancelled' : 'pending'; // Reject reverts to pending

    // --- Update Reservation Status ---
    $stmtUpdate = $pdo->prepare("
        UPDATE venue_reservations
        SET status = :new_status, updated_at = NOW()
        WHERE id = :reservation_id AND status = 'cancellation_requested' -- Add status check again for safety
    ");
    $stmtUpdate->execute([
        ':new_status' => $newStatus,
        ':reservation_id' => $reservationId
    ]);

    // Check if the update was successful
    if ($stmtUpdate->rowCount() > 0) {
        // --- Add Notification for the User (Booker) ---
        // Notify the user who made the reservation about the client's decision
        try {
            // Ensure the reservation has a user_id (not a guest booking, though guest notifications are a separate consideration)
            if (!empty($reservation['user_id'])) {
                 $notificationMessage = "";
                 $notificationStatusChangedTo = "";

                 if ($action === 'approve') {
                     $notificationMessage = "Your cancellation request for reservation ID {$reservationId} has been approved by the venue owner. The reservation is now cancelled.";
                     $notificationStatusChangedTo = 'cancelled';
                 } else { // action === 'reject'
                     $notificationMessage = "Your cancellation request for reservation ID {$reservationId} has been rejected by the venue owner. The reservation status is now pending.";
                     $notificationStatusChangedTo = 'pending'; // Or the status it was before requesting cancellation
                 }

                 $stmtInsertNotification = $pdo->prepare("
                     INSERT INTO user_notifications (user_id, reservation_id, message, status_changed_to)
                     VALUES (:user_id, :reservation_id, :message, :status_changed_to)
                 ");
                 $stmtInsertNotification->execute([
                     ':user_id' => $reservation['user_id'], // The user who made the reservation
                     ':reservation_id' => $reservationId,
                     ':message' => $notificationMessage,
                     ':status_changed_to' => $notificationStatusChangedTo
                 ]);
            }
        } catch (PDOException $e) {
            // Log the notification error but don't prevent the action success
            error_log("Error creating user notification after client cancellation action: " . $e->getMessage());
        }
        // --- End Notification ---


        // Redirect back to the client's reservation management page with a success message
        $successParam = ($action === 'approve') ? 'cancellation_approved' : 'cancellation_rejected';
        header('Location: ' . $redirectUrl . '?action_success=' . $successParam);
        exit;

    } else {
        // If no rows were affected, something went wrong (e.g., status changed just before update)
        error_log("Client cancellation action update failed for reservation ID {$reservationId}, action {$action}. No rows affected. Status might have changed.");
        header('Location: ' . $redirectUrl . '?action_error=db_update_failed');
        exit;
    }

} catch (PDOException $e) {
    // Catch any database errors
    error_log("Database Error processing client cancellation action (Res ID: {$reservationId}, Action: {$action}, Client ID: {$loggedInClientId}): " . $e->getMessage());
    header('Location: ' . $redirectUrl . '?action_error=db_error');
    exit;
} catch (Exception $e) {
    // Catch other exceptions
    error_log("Application Error processing client cancellation action (Res ID: {$reservationId}, Action: {$action}, Client ID: {$loggedInClientId}): " . $e->getMessage());
    header('Location: ' . $redirectUrl . '?action_error=internal_error'); // Generic error for user
    exit;
}
?>
