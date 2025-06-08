<?php
session_start();

// Include the database connection
// Adjust the path below to your db_connection.php file as necessary based on where process_reservation.php is located
// Example: If process_reservation.php is in a 'client' subdirectory and includes is in the parent, use '../includes/db_connection.php'
require_once __DIR__ . '/includes/db_connection.php';// **ADJUST PATH AS NEEDED based on your file structure**


// Check if the user is logged in and is a client
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'client') {
    $_SESSION['error_message'] = "You must be logged in as a client to make a reservation.";
    // Adjust path as needed
    header("Location: /ventech_locator/client/client_login.php");
    exit();
}

$loggedInUserId = $_SESSION['user_id'];

// Ensure the request is a POST request
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['error_message'] = "Invalid request method.";
    // Redirect to the form page, including venue details if possible
     // Adjust path as needed, potentially passing venue_id back
     $redirectUrl = '/ventech_locator/venue_reservation_form.php';
     // Consider adding venue_id back to the URL if the form page needs it via GET
     // if (isset($_POST['venue_id'])) $redirectUrl .= '?venue_id=' . urlencode($_POST['venue_id']);
     header("Location: " . $redirectUrl);
    exit();
}

// Initialize error messages
$errors = [];


// --- Retrieve and Sanitize Data from POST ---
// Ensure these names match the 'name' attributes in your venue_reservation_form.php form
$venueId = filter_input(INPUT_POST, 'venue_id', FILTER_SANITIZE_NUMBER_INT);
$venueName = filter_input(INPUT_POST, 'venue_name', FILTER_SANITIZE_STRING); // From hidden field
$eventDate = trim($_POST['event_date'] ?? '');
$startTime = trim($_POST['start_time'] ?? '');
$endTime = trim($_POST['end_time'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$mobileCountryCode = trim($_POST['mobile_country_code'] ?? ''); // Optional
$mobileNumber = trim($_POST['mobile_number'] ?? ''); // Optional
$address = trim($_POST['address'] ?? ''); // Optional
$country = trim($_POST['country'] ?? ''); // Optional
$notes = trim($_POST['notes'] ?? ''); // Optional
$voucherCode = trim($_POST['voucher_code'] ?? ''); // Optional
// ... retrieve other form fields (attendees, purpose, etc.) based on your form ...


// --- Server-Side Validation ---
if (empty($venueId) || $venueId <= 0) {
    $errors[] = "Venue information is missing or invalid.";
}
if (empty($eventDate)) {
    $errors[] = "Event date is required.";
} elseif (strtotime($eventDate) === false || $eventDate < date('Y-m-d')) {
     $errors[] = "A valid future event date is required.";
}
if (empty($startTime)) {
    $errors[] = "Start time is required.";
}
if (empty($endTime)) {
    $errors[] = "End time is required.";
} elseif (!empty($startTime) && strtotime($endTime) !== false && strtotime($startTime) !== false && strtotime($endTime) <= strtotime($startTime)) {
    $errors[] = "End time must be after start time.";
}
if (empty($firstName)) {
    $errors[] = "First name is required.";
}
if (empty($lastName)) {
    $errors[] = "Last name is required.";
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "A valid email address is required.";
}
// Add more validation for other fields as needed (e.g., phone format, address length, etc.)


// --- Process Data if No Validation Errors ---
if (empty($errors)) {
    try {
        // Assuming $pdo is available from db_connection.php

        // 1. Verify Venue Exists and get Price (Optional but recommended)
        $stmtVenue = $pdo->prepare("SELECT price FROM venue WHERE id = ?");
        $stmtVenue->execute([$venueId]);
        $venueDetails = $stmtVenue->fetch();

        if (!$venueDetails) {
            $errors[] = "Selected venue not found.";
        } else {
            $venuePricePerHour = $venueDetails['price'];

            // 2. Calculate Total Cost (Server-Side)
            $startDatetime = new DateTime($eventDate . ' ' . $startTime);
            $endDatetime = new DateTime($eventDate . ' ' . $endTime);

            if ($startDatetime < $endDatetime) {
                 $interval = $startDatetime->diff($endDatetime);
                 // Calculate duration in hours (including minutes as fraction)
                 $durationHours = $interval->days * 24 + $interval->h + ($interval->i / 60);
                 $totalCost = $durationHours * $venuePricePerHour;
            } else {
                 // This case should ideally be caught by the validation above, but as a fallback:
                 $errors[] = "Invalid time slot selected for calculation.";
                 $totalCost = 0;
            }


            // 3. Check for time conflicts for the selected venue and date (Crucial for booking system)
            // This is a basic example, you'd need more robust conflict checking
            /*
            $stmtConflict = $pdo->prepare("
                SELECT COUNT(*) FROM venue_reservations
                WHERE venue_id = ? AND event_date = ?
                AND (
                    (start_time < ? AND end_time > ?) -- Existing booking starts before and ends after new one
                    OR (start_time >= ? AND start_time < ?) -- Existing booking starts within new one
                    OR (end_time > ? AND end_time <= ?) -- Existing booking ends within new one
                    OR (start_time <= ? AND end_time >= ?) -- New booking is fully within existing one
                )
                AND status IN ('pending', 'confirmed') -- Only check against active bookings
            ");
            $stmtConflict->execute([
                $venueId, $eventDate,
                $endTime, $startTime, // Check if new booking overlaps with existing
                $startTime, $endTime,
                $startTime, $endTime,
                $startTime, $endTime
            ]);
            $conflictCount = $stmtConflict->fetchColumn();

            if ($conflictCount > 0) {
                $errors[] = "The selected time slot is already booked for this venue.";
            }
            */


            // 4. Insert Reservation into Database if no errors so far
            if (empty($errors)) { // Check errors again (including conflict check errors if implemented)
                $stmtInsert = $pdo->prepare("
                    INSERT INTO venue_reservations (
                        user_id, venue_id, event_date, start_time, end_time,
                        first_name, last_name, email, mobile_country_code, mobile_number,
                        address, country, notes, voucher_code, status, total_cost, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, NOW()
                    )
                ");

                $status = 'pending'; // Default status for a new reservation

                $stmtInsert->execute([
                    $loggedInUserId, $venueId, $eventDate, $startTime, $endTime,
                    $firstName, $lastName, $email, $mobileCountryCode, $mobileNumber,
                    $address, $country, $notes, $voucherCode, $status, $totalCost
                    // ... include other fields like attendees, purpose if added to DB schema and form ...
                ]);

                $lastInsertId = $pdo->lastInsertId(); // Get the ID of the newly inserted row

                // --- Set Success Message and Redirect ---
                $_SESSION['success_message'] = "Your reservation request for " . htmlspecialchars($venueName) . " on " . htmlspecialchars($eventDate) . " has been submitted. Reservation ID: " . $lastInsertId;
                $_SESSION['last_reservation_id'] = $lastInsertId; // Store the ID for summary display on manage page

                // Adjust redirect path as needed
                header("Location: /ventech_locator/reservation_manage.php");
                exit();

            } // End if empty($errors) before insertion

        } // End if venueDetails found

    } catch (PDOException $e) {
        error_log("Database error during reservation processing: " . $e->getMessage());
        // --- TEMPORARY: Display the specific error for debugging ---
        $errors[] = "A database error occurred while submitting your reservation. Please try again.<br>Debug: " . htmlspecialchars($e->getMessage());
        // **IMPORTANT:** Remove the "<br>Debug:..." part and $e->getMessage() for production!
        // --- END TEMPORARY ---
    }
}

// --- If there are errors (validation or database) ---
if (!empty($errors)) {
    // Store errors and potentially form data in session before redirecting back to the form page
    $_SESSION['error_message'] = "Please correct the following issues:<br>" . implode("<br>", $errors);
    // Store raw POST data to repopulate the form on the previous page
    $_SESSION['form_data'] = $_POST;

    // Redirect back to the form page (venue_reservation_form.php)
    // You might need to pass venue_id and venue_name back via GET if your form page needs them this way
     // ADJUST PATH AS NEEDED
     $redirectUrl = '/ventech_locator/venue_reservation_form.php';
     $getParams = [];
     // Pass back venue details and potentially selected date/times if the form page expects them via GET to repopulate context
     if (!empty($venueId)) $getParams['venue_id'] = $venueId;
     if (!empty($venueName)) $getParams['venue_name'] = urlencode($venueName);
     if (!empty($_POST['event_date'])) $getParams['event_date'] = $_POST['event_date'];
     // Add other GET parameters that venue_reservation_form.php might need if it was linked to with GET

     if (!empty($getParams)) {
         $redirectUrl .= '?' . http_build_query($getParams);
     }

    header("Location: " . $redirectUrl);
    exit();
}

// If the script somehow finishes without redirecting (shouldn't happen with successful execution or errors)
// Default redirect
header("Location: /ventech_locator/reservation_manage.php");
exit();

?>