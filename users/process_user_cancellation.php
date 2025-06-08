<?php
// Start the session to access user data
session_start();

// Include the database connection file
// Ensure this path is correct relative to process_user_cancellation.php
// Assuming includes folder is one level up from the directory containing process_user_cancellation.php (e.g., users/)
require_once '../includes/db_connection.php'; // Adjust path if needed

// Define the URL to redirect back to on completion or error
$redirectUrl = 'user_reservation_manage.php'; // Adjust path if needed

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect to the login page with an error
    header('Location: ' . $redirectUrl . '?cancel_error=unauthenticated');
    exit; // Stop script execution
}

// Get the logged-in user's ID
$loggedInUserId = $_SESSION['user_id'];

// Check if the reservation ID was submitted via POST
// Ensure it's a POST request to prevent direct URL access for actions
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['reservation_id'])) {
    // If not a POST request or ID is missing, redirect with an error
    header('Location: ' . $redirectUrl . '?cancel_error=invalid_id');
    exit;
}

// Get and sanitize the reservation ID from the POST data
$reservationId = filter_var($_POST['reservation_id'], FILTER_SANITIZE_NUMBER_INT);

// Validate the reservation ID - ensure it's a positive integer
if (!$reservationId || $reservationId <= 0) {
     header('Location: ' . $redirectUrl . '?cancel_error=invalid_id');
     exit;
}

try {
    // Ensure PDO connection is available after including the file
    if (!isset($pdo) || !$pdo instanceof PDO) {
        // If PDO is not available, throw an exception
        throw new Exception("Database connection not available.");
    }

    // --- IMPORTANT VALIDATION ---
    // Fetch the reservation details to verify:
    // 1. It exists.
    // 2. It belongs to the currently logged-in user.
    // 3. It is in a status that is allowed to *request* cancellation.
    // We also fetch venue_id to find the venue owner for notification.
    $stmt = $pdo->prepare("
        SELECT id, user_id, status, venue_id
        FROM venue_reservations
        WHERE id = :reservation_id AND user_id = :user_id
        LIMIT 1 -- Limit to 1 as we expect only one matching reservation
    ");
    $stmt->execute([':reservation_id' => $reservationId, ':user_id' => $loggedInUserId]);
    $reservation = $stmt->fetch(); // Fetch the single row

    // Check if the reservation was found and belongs to the user
    if (!$reservation) {
        // If no reservation found for this ID and user, redirect with error
        header('Location: ' . $redirectUrl . '?cancel_error=not_found');
        exit;
    }

    // Define statuses FROM which a user can REQUEST cancellation
    // This should match the logic in user_reservation_manage.php for showing the button
    $requestCancellableStatuses = ['pending', 'accepted', 'confirmed'];

    // Check if the current status of the reservation allows requesting cancellation
    if (!in_array(strtolower($reservation['status']), $requestCancellableStatuses)) {
        // If the status is not in the list allowing a request, redirect with error
        header('Location: ' . $redirectUrl . '?cancel_error=status_not_cancellable');
        exit;
    }

    // --- Update Reservation Status to 'cancellation_requested' ---
    // Prepare the SQL statement to update the status to the intermediate state
    $stmtUpdate = $pdo->prepare("
        UPDATE venue_reservations
        SET status = 'cancellation_requested', updated_at = NOW()
        WHERE id = :reservation_id AND user_id = :user_id -- Double check user_id in WHERE clause for safety
    ");
    // Execute the update statement
    $stmtUpdate->execute([':reservation_id' => $reservationId, ':user_id' => $loggedInUserId]);

    // Check if the update was successful by checking the number of affected rows
    if ($stmtUpdate->rowCount() > 0) {
        // --- Add Notification for the Venue Owner ---
        // Notify the venue owner that the user has requested cancellation
        try {
            // Find the owner of the venue associated with this reservation
            $stmtOwner = $pdo->prepare("
                SELECT user_id FROM venue WHERE id = :venue_id LIMIT 1
            ");
            $stmtOwner->execute([':venue_id' => $reservation['venue_id']]);
            $venueOwner = $stmtOwner->fetch();

            // If a venue owner is found (and it's not the user themselves)
            if ($venueOwner && $venueOwner['user_id'] !== $loggedInUserId) {
                 // Craft the notification message for the owner
                 $notificationMessage = "Cancellation requested for a booking (ID: {$reservationId}) for your venue (ID: {$reservation['venue_id']}).";

                 // Prepare and execute the INSERT statement for the notification
                 $stmtInsertNotification = $pdo->prepare("
                     INSERT INTO user_notifications (user_id, reservation_id, message, status_changed_to)
                     VALUES (:user_id, :reservation_id, :message, :status_changed_to)
                 ");
                 $stmtInsertNotification->execute([
                     ':user_id' => $venueOwner['user_id'], // The user ID of the venue owner
                     ':reservation_id' => $reservationId,
                     ':message' => $notificationMessage,
                     ':status_changed_to' => 'cancellation_requested' // Record the new status
                 ]);
            }
        } catch (PDOException $e) {
            // Log the notification error but do not prevent the status update success
            error_log("Error creating cancellation request notification for venue owner: " . $e->getMessage());
        }
        // --- End Notification ---


        // Redirect back to the manage page with a success message
        // Indicate that the cancellation was *requested*
        header('Location: ' . $redirectUrl . '?cancel_success=requested');
        exit;

    } else {
        // If no rows were affected by the update, it might mean the status
        // was already 'cancellation_requested' or changed just before.
        // Log this unusual event.
        error_log("Cancellation request update failed for reservation ID {$reservationId}, user ID {$loggedInUserId}. No rows affected. Status might have changed.");
        // Redirect with a database error message
        header('Location: ' . $redirectUrl . '?cancel_error=db_error');
        exit;
    }

} catch (PDOException $e) {
    // Catch any database errors during the process
    error_log("Database Error processing user cancellation request (Res ID: {$reservationId}, User ID: {$loggedInUserId}): " . $e->getMessage());
    // Redirect with a generic database error message
    header('Location: ' . $redirectUrl . '?cancel_error=db_error');
    exit;
} catch (Exception $e) {
    // Catch any other application-level exceptions
    error_log("Application Error processing user cancellation request (Res ID: {$reservationId}, User ID: {$loggedInUserId}): " . $e->getMessage());
    // Redirect with a generic database error message (hiding internal details)
    header('Location: ' . $redirectUrl . '?cancel_error=db_error');
    exit;
}
?>
