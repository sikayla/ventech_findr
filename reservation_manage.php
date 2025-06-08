<?php
// **1. Start Session** (MUST be the very first thing)
// Ensure this is the absolute first line, before any output (including whitespace)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ** CSRF Protection Setup for Action Forms **
// Generate a fresh token for the action forms if one doesn't exist
// This token will be included in the hidden input field in the accept/reject forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


require_once 'includes/db_connection.php'; // Ensure this path is correct and it initializes $pdo

// Ensure $pdo is available from the included file
if (!isset($pdo)) {
    // This indicates an issue with db_connection.php
    error_log("PDO connection failed in reservation_manage.php");
    // Set a user-friendly error message in session
    $_SESSION['message'] = "System error: Database connection failed. Please try again later.";
    $_SESSION['message_type'] = 'error';
    // Redirect to a general page or error page
    header("Location: /ventech_locator/index.php"); // Adjust redirect path as needed
    exit();
}


// Initialize message variable from session or GET parameter if redirected
$message = '';
$message_type = ''; // To indicate success/error/info

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info'; // Default to info if type is not set
    unset($_SESSION['message']); // Clear the message after displaying
    unset($_SESSION['message_type']); // Clear message type
}

// ** Add Status Filter for Display **
$filter_status = $_GET['status_filter'] ?? 'all'; // Default to 'all' if no filter is set
// Define allowed statuses for filtering
$allowed_filter_statuses = ['all', 'pending', 'accepted', 'rejected', 'cancelled', 'cancellation_requested', 'completed'];
if (!in_array($filter_status, $allowed_filter_statuses)) {
    $filter_status = 'all'; // Reset to 'all' if an invalid filter is provided
}


// Reservation form submission via AJAX (This block seems unrelated to owner management, keeping it as is)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['venue_id'], $_POST['start_time'], $_POST['end_time']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    try {
        // Validate required fields for submission
        $required_fields = ['venue_id', 'event_date', 'start_time', 'end_time', 'first_name', 'last_name', 'email'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                // Return specific error for missing field
                echo 'Error: Missing required field: ' . htmlspecialchars($field);
                exit;
            }
        }

        // Basic validation for date and time formats (optional, but good practice)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['event_date'])) {
             echo 'Error: Invalid date format.';
             exit;
        }
         if (!preg_match('/^\d{2}:\d{2}$/', $_POST['start_time']) || !preg_match('/^\d{2}:\d{2}$/', $_POST['end_time'])) {
             echo 'Error: Invalid time format.';
             exit;
         }

        // Convert times to timestamps for duration calculation
        $start_timestamp = strtotime($_POST['start_time']);
        $end_timestamp = strtotime($_POST['end_time']);

        // Validate times
        if ($end_timestamp <= $start_timestamp) {
            echo 'Error: End time must be after start time.';
            exit;
        }

        // Get venue details including price per hour from the database
        $stmt = $pdo->prepare("SELECT price FROM venue WHERE id = :venue_id"); // Assuming 'price' column name for price per hour
        $stmt->execute([':venue_id' => $_POST['venue_id']]);
        $venue = $stmt->fetch();

        if ($venue && isset($venue['price'])) {
            $venue_price_per_hour = (float) $venue['price'];

            // Calculate duration and total cost
            $duration_in_seconds = $end_timestamp - $start_timestamp;
            $duration_in_hours = $duration_in_seconds / 3600; // Convert seconds to hours

            // Ensure duration is positive and calculate cost
            if ($duration_in_hours > 0) {
                $total_cost = $venue_price_per_hour * $duration_in_hours;

                // Insert reservation with calculated total cost AND price per hour
                $reservationStmt = $pdo->prepare("
                    INSERT INTO venue_reservations
                    (venue_id, user_id, event_date, start_time, end_time, first_name, last_name, email, mobile_country_code, mobile_number, address, country, notes, voucher_code, total_cost, price_per_hour, status)
                    VALUES
                    (:venue_id, :user_id, :event_date, :start_time, :end_time, :first_name, :last_name, :email, :mobile_country_code, :mobile_number, :address, :country, :notes, :voucher_code, :total_cost, :price_per_hour, 'pending')
                ");

                // Ensure all parameters expected by the INSERT statement are bound
                 $reservationStmt->execute([
                    ':venue_id' => $_POST['venue_id'],
                    ':user_id' => $_POST['user_id'] ?? null, // Use null if user_id is not set (e.g., not logged in)
                    ':event_date' => $_POST['event_date'],
                    ':start_time' => $_POST['start_time'],
                    ':end_time' => $_POST['end_time'],
                    ':first_name' => $_POST['first_name'],
                    ':last_name' => $_POST['last_name'],
                    ':email' => $_POST['email'],
                    ':mobile_country_code' => $_POST['mobile_country_code'] ?? '', // Default to empty string if not set
                    ':mobile_number' => $_POST['mobile_number'] ?? '', // Default to empty string if not set
                    ':address' => $_POST['address'] ?? '', // Added address
                    ':country' => $_POST['country'] ?? '', // Added country
                    ':notes' => $_POST['notes'] ?? '', // Default to empty string if not set
                    ':voucher_code' => $_POST['voucher_code'] ?? null, // Default to null if not set
                    ':total_cost' => $total_cost,
                    ':price_per_hour' => $venue_price_per_hour // Save the fetched price per hour
                 ]);


                // If the insertion was successful, return 'success'
                echo 'success';
                exit;

            } else {
                echo 'Error: Calculated duration is not positive. Check start and end times.';
                exit;
            }

        } else {
            echo 'Error: Venue not found or price not available.';
            exit;
        }
    } catch (PDOException $e) {
        // Log the database error for debugging
        error_log("Database error during reservation submission: " . $e->getMessage());
        // Return a generic error message to the user
        echo 'An error occurred during reservation submission.';
        exit;
    }
}

// Handle reservation status update (Owner actions: accept, reject, confirm_cancellation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'], $_POST['action']) && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
     // Validate CSRF token for security
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = "Security error: Invalid request token.";
        $_SESSION['message_type'] = 'error';
        // Redirect back to the same page to show the error
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    $reservation_id = $_POST['reservation_id'];
    $action = $_POST['action']; // 'accept', 'reject', or 'confirm_cancellation'
    $new_status = null;

    switch ($action) {
        case 'accept':
            $new_status = 'accepted';
            break;
        case 'reject':
            $new_status = 'rejected';
            break;
        case 'confirm_cancellation':
            // Only allow confirmation if current status is 'cancellation_requested'
            $stmtCheckStatus = $pdo->prepare("SELECT status FROM venue_reservations WHERE id = :id");
            $stmtCheckStatus->execute([':id' => $reservation_id]);
            $current_res_status = $stmtCheckStatus->fetchColumn();

            if ($current_res_status === 'cancellation_requested') {
                $new_status = 'cancelled'; // Final status after owner confirms cancellation
            } else {
                $_SESSION['message'] = "Cannot confirm cancellation for reservation #{$reservation_id}. Status is '{$current_res_status}'.";
                $_SESSION['message_type'] = 'warning';
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            }
            break;
        default:
            $_SESSION['message'] = "Invalid action specified.";
            $_SESSION['message_type'] = 'error';
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
    }

    if ($new_status) {
        try {
            $stmt = $pdo->prepare("
                UPDATE venue_reservations
                SET status = :status, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':status' => $new_status,
                ':id' => $reservation_id
            ]);

            $_SESSION['message'] = "Reservation status updated to '" . htmlspecialchars($new_status) . "' successfully.";
            $_SESSION['message_type'] = 'success'; // Set message type for success

        } catch (PDOException $e) {
            error_log("Failed to update reservation status: " . $e->getMessage());
            $_SESSION['message'] = "An error occurred while updating the reservation status.";
            $_SESSION['message_type'] = 'error'; // Set message type for error
        }
    }


    // Redirect to avoid resubmission on refresh
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}


// Fetch reservations for the client/owner (assuming this page is for the venue owner)
if (!isset($_SESSION['user_id'])) {
    header("Location: /ventech_locator/client/client_login.php?error=not_logged_in");
    exit;
}
$loggedInUserId = $_SESSION['user_id']; // Assuming client/owner ID is stored in session

// --- Check if a specific reservation ID is requested for viewing details ---
$view_reservation_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$single_reservation_details = null;

if ($view_reservation_id) {
    try {
        // Fetch the specific reservation details
        $stmtSingleRes = $pdo->prepare("
            SELECT
                r.id, r.venue_id, r.user_id AS booker_user_id, r.event_date, r.start_time, r.end_time,
                r.first_name, r.last_name, r.email AS booker_email, r.mobile_country_code, r.mobile_number,
                r.address, r.country, r.notes, r.voucher_code, r.total_cost, r.price_per_hour, r.status, r.created_at, r.updated_at,
                v.title AS venue_name,
                v.image_path,
                u_booker.username AS booker_username
            FROM venue_reservations r
            JOIN venue v ON r.venue_id = v.id
            LEFT JOIN users u_booker ON r.user_id = u_booker.id
            WHERE r.id = ? AND v.user_id = ?
        ");
        $stmtSingleRes->execute([$view_reservation_id, $loggedInUserId]);
        $single_reservation_details = $stmtSingleRes->fetch(PDO::FETCH_ASSOC);

        if (!$single_reservation_details) {
            // If reservation not found or not owned by current user
            $_SESSION['message'] = "Reservation not found or you don't have permission to view it.";
            $_SESSION['message_type'] = 'error';
            // Redirect back to the main list
            header("Location: reservation_manage.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch single reservation details: " . $e->getMessage());
        $_SESSION['message'] = "An error occurred while loading reservation details.";
        $_SESSION['message_type'] = 'error';
        header("Location: reservation_manage.php");
        exit();
    }
} else {
    // --- Fetch all reservations if no specific ID is requested ---
    $reservations = [];
    try {
        // First, get the venue IDs owned by the logged-in user
        $stmtVenueIds = $pdo->prepare("SELECT id FROM venue WHERE user_id = ?");
        $stmtVenueIds->execute([$loggedInUserId]);
        $venue_ids_owned = $stmtVenueIds->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($venue_ids_owned)) {
            // Prepare placeholders for IN clause
            $in_placeholders = implode(',', array_fill(0, count($venue_ids_owned), '?'));
            // Fetch reservations for those venue IDs, including the saved price_per_hour
            $sql = "SELECT
                        r.id, r.venue_id, r.user_id AS booker_user_id, r.event_date, r.start_time, r.end_time,
                        r.first_name, r.last_name, r.email AS booker_email, r.mobile_country_code, r.mobile_number,
                        r.country, r.notes, r.total_cost, r.price_per_hour, r.status, r.created_at, r.updated_at,
                        v.title AS venue_name,
                        v.image_path,
                        u_booker.username AS booker_username
                    FROM venue_reservations r
                    JOIN venue v ON r.venue_id = v.id
                    LEFT JOIN users u_booker ON r.user_id = u_booker.id
                    WHERE r.venue_id IN ($in_placeholders)";

            $params = $venue_ids_owned; // Start with venue IDs as parameters

            // Add status filter condition if a valid filter is provided
            if ($filter_status !== 'all') {
                // Special handling for 'cancelled' to include 'cancellation_requested' if desired
                if ($filter_status === 'cancelled') {
                    $sql .= " AND (r.status = 'cancelled' OR r.status = 'cancellation_requested')";
                } else {
                    $sql .= " AND r.status = ?";
                    $params[] = $filter_status;
                }
            }

            $sql .= " ORDER BY r.created_at DESC";

            $stmtReservations = $pdo->prepare($sql);
            $stmtReservations->execute($params);
            $reservations = $stmtReservations->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // User owns no venues, so no reservations to show
            $reservations = [];
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch reservations for user ID {$loggedInUserId}: " . $e->getMessage());
        $reservations = [];
        // Set a user-friendly error message
        $message = "An error occurred while loading reservations. Please try again later.";
        $message_type = 'error';
    }
}


// Helper function to get status badge class (copied for consistency)
function getStatusBadgeClass($status) {
    $status = strtolower($status ?? 'unknown');
    switch ($status) {
        case 'open': case 'confirmed': case 'accepted': case 'completed':
            return 'bg-green-100 text-green-800';
        case 'closed': case 'cancelled': case 'rejected':
            return 'bg-red-100 text-red-800';
        case 'pending': case 'cancellation_requested': // Add cancellation_requested here
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Helper function to determine message alert class (copied for consistency)
function getMessageAlertClass($type) {
    switch ($type) {
        case 'success': return 'bg-green-100 border-green-500 text-green-700';
        case 'error':   return 'bg-red-100 border-red-500 text-red-700';
        case 'warning': return 'bg-yellow-100 border-yellow-500 text-yellow-700';
        case 'info':
        default:        return 'bg-blue-100 border-blue-500 text-blue-700';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Reservations - Ventech Locator</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" xintegrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
     <link rel="stylesheet" href="/ventech_locator/css/reservation_manage.css">
    <style>
      
    </style>
</head>
<body class="bg-gray-100 p-4 md:p-6 lg:p-8">
    <div id="loading-overlay" class="flex">
        <div class="spinner"></div>
    </div>

    <div class="max-w-7xl mx-auto">
        <header class="bg-white shadow-md rounded-lg p-6 mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-1">Manage Venue Reservations</h1>
                <p class="text-gray-600 text-sm">Review and manage booking requests for your venues.</p>
            </div>
             <a href="client_dashboard.php" class="bg-orange-500 hover:bg-orange-600 text-white py-2 px-4 rounded text-sm font-medium transition duration-150 ease-in-out shadow-sm">
                 <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
             </a>
        </header>

        <?php // Display messages from PHP processing ?>
        <?php if (!empty($message)): ?>
            <div class="message-alert <?= getMessageAlertClass($message_type) ?>" role="alert">
                 <?php if ($message_type === 'success'): ?>
                     <strong class="font-bold block mb-1"><i class="fas fa-check-circle mr-2"></i>Success!</strong>
                 <?php elseif ($message_type === 'error'): ?>
                     <strong class="font-bold block mb-1"><i class="fas fa-exclamation-triangle mr-2"></i>Error:</strong>
                 <?php elseif ($message_type === 'warning'): ?>
                      <strong class="font-bold block mb-1"><i class="fas fa-exclamation-circle mr-2"></i>Warning:</strong>
                 <?php else: ?>
                      <strong class="font-bold block mb-1"><i class="fas fa-info-circle mr-2"></i>Information:</strong>
                 <?php endif; ?>
                <span class="block text-sm"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($single_reservation_details): // Display single reservation details ?>
            <div class="bg-white shadow-md rounded-lg p-6 mb-8">
                <div class="flex justify-between items-center mb-4 border-b pb-3">
                    <h2 class="text-xl font-bold text-gray-800">Reservation Details #<?= htmlspecialchars($single_reservation_details['id']) ?></h2>
                    <a href="reservation_manage.php<?= ($filter_status !== 'all' ? '?status_filter=' . htmlspecialchars($filter_status) : '') ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        <i class="fas fa-arrow-left mr-1"></i> Back to List
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-gray-700">
                    <div>
                        <p class="mb-2"><strong class="text-gray-900">Venue:</strong> <a href="/ventech_locator/venue_display.php?id=<?= htmlspecialchars($single_reservation_details['venue_id']) ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars($single_reservation_details['venue_name']) ?></a></p>
                        <p class="mb-2"><strong class="text-gray-900">Booked by:</strong> <?= htmlspecialchars($single_reservation_details['first_name'] . ' ' . $single_reservation_details['last_name']) ?> (<?= htmlspecialchars($single_reservation_details['booker_username'] ?? 'N/A') ?>)</p>
                        <p class="mb-2"><strong class="text-gray-900">Email:</strong> <?= htmlspecialchars($single_reservation_details['booker_email']) ?></p>
                        <p class="mb-2"><strong class="text-gray-900">Phone:</strong> <?= htmlspecialchars($single_reservation_details['mobile_country_code'] . $single_reservation_details['mobile_number']) ?></p>
                        <p class="mb-2"><strong class="text-gray-900">Address:</strong> <?= htmlspecialchars($single_reservation_details['address'] ?? 'N/A') ?></p>
                        <p class="mb-2"><strong class="text-gray-900">Country:</strong> <?= htmlspecialchars($single_reservation_details['country'] ?? 'N/A') ?></p>
                    </div>
                    <div>
                        <p class="mb-2"><strong class="text-gray-900">Event Date:</strong> <?= htmlspecialchars(date("F d, Y", strtotime($single_reservation_details['event_date']))) ?></p>
                        <p class="mb-2"><strong class="text-gray-900">Time:</strong> <?= htmlspecialchars(date("g:i A", strtotime($single_reservation_details['start_time']))) ?> - <?= htmlspecialchars(date("g:i A", strtotime($single_reservation_details['end_time']))) ?></p>
                        <p class="mb-2"><strong class="text-gray-900">Price per Hour:</strong> ₱<?= number_format($single_reservation_details['price_per_hour'], 2) ?></p>
                        <p class="mb-2"><strong class="text-gray-900">Total Cost:</strong> ₱<?= number_format($single_reservation_details['total_cost'], 2) ?></p>
                        <p class="mb-2"><strong class="text-gray-900">Status:</strong> <span class="px-2 py-0.5 inline-block rounded-full text-xs font-semibold <?= getStatusBadgeClass($single_reservation_details['status']) ?>"><?= htmlspecialchars(ucfirst($single_reservation_details['status'])) ?></span></p>
                        <p class="mb-2"><strong class="text-gray-900">Requested On:</strong> <?= htmlspecialchars(date("M d, Y H:i", strtotime($single_reservation_details['created_at']))) ?></p>
                        <?php if (!empty($single_reservation_details['updated_at'])): ?>
                            <p class="mb-2"><strong class="text-gray-900">Last Updated:</strong> <?= htmlspecialchars(date("M d, Y H:i", strtotime($single_reservation_details['updated_at']))) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($single_reservation_details['voucher_code'])): ?>
                            <p class="mb-2"><strong class="text-gray-900">Voucher Code:</strong> <?= htmlspecialchars($single_reservation_details['voucher_code']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($single_reservation_details['notes'])): ?>
                    <div class="mt-6 pt-4 border-t border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800 mb-2">Notes from Booker:</h3>
                        <p class="text-gray-700 bg-gray-50 p-3 rounded-md border border-gray-200"><?= nl2br(htmlspecialchars($single_reservation_details['notes'])) ?></p>
                    </div>
                <?php endif; ?>

                <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                    <?php if (strtolower($single_reservation_details['status']) === 'pending'): ?>
                        <form method="POST" action="reservation_manage.php" class="inline-block">
                            <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($single_reservation_details['id']) ?>">
                            <input type="hidden" name="action" value="accept">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded text-sm font-medium transition duration-150 ease-in-out shadow-sm">
                                <i class="fas fa-check mr-1"></i> Accept
                            </button>
                        </form>
                        <form method="POST" action="reservation_manage.php" class="inline-block">
                            <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($single_reservation_details['id']) ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded text-sm font-medium transition duration-150 ease-in-out shadow-sm">
                                <i class="fas fa-times mr-1"></i> Reject
                            </button>
                        </form>
                    <?php elseif (strtolower($single_reservation_details['status']) === 'cancellation_requested'): ?>
                        <form method="POST" action="reservation_manage.php" class="inline-block">
                            <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($single_reservation_details['id']) ?>">
                            <input type="hidden" name="action" value="confirm_cancellation">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded text-sm font-medium transition duration-150 ease-in-out shadow-sm">
                                <i class="fas fa-check-double mr-1"></i> Confirm Cancellation
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if (strtolower($single_reservation_details['status']) === 'accepted' || strtolower($single_reservation_details['status']) === 'completed'): ?>
                        <a href="/ventech_locator/print_receipt.php?id=<?= htmlspecialchars($single_reservation_details['id']) ?>" target="_blank" class="bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded text-sm font-medium transition duration-150 ease-in-out shadow-sm">
                            <i class="fas fa-print mr-1"></i> Print Receipt
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: // Display list of reservations ?>
            <div class="mb-6 flex items-center justify-end">
                <label for="status-filter" class="text-gray-700 text-sm font-medium mr-2">Filter by Status:</label>
                <select id="status-filter" onchange="window.location.href='reservation_manage.php?status_filter='+this.value" class="border-gray-300 rounded-md shadow-sm focus:border-orange-500 focus:ring focus:ring-orange-200 focus:ring-opacity-50 py-1.5 px-3">
                    <option value="all" <?= ($filter_status === 'all') ? 'selected' : '' ?>>All</option>
                    <option value="pending" <?= ($filter_status === 'pending') ? 'selected' : '' ?>>Pending</option>
                    <option value="accepted" <?= ($filter_status === 'accepted') ? 'selected' : '' ?>>Accepted</option>
                    <option value="rejected" <?= ($filter_status === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                    <option value="cancellation_requested" <?= ($filter_status === 'cancellation_requested') ? 'selected' : '' ?>>Cancellation Requested</option>
                    <option value="cancelled" <?= ($filter_status === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    <option value="completed" <?= ($filter_status === 'completed') ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>


            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (count($reservations) > 0): ?>
                    <?php foreach ($reservations as $res): ?>
                        <?php
                            // Image path logic (similar to dashboard)
                            $imagePathFromDB = $res['image_path'] ?? null;
                            $uploadsBaseUrl = '/ventech_locator/uploads/'; // ADJUST PATH IF NEEDED! Relative to web root.
                            $placeholderImg = 'https://via.placeholder.com/400x250/fbbf24/ffffff?text=No+Image';
                            $imgSrc = $placeholderImg;
                            if (!empty($imagePathFromDB)) {
                                 // Ensure path is correctly formed and HTML escaped
                                $imgSrc = $uploadsBaseUrl . ltrim(htmlspecialchars($imagePathFromDB), '/');
                            }
                        ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden flex flex-col transition duration-300 ease-in-out hover:shadow-lg">
                             <a href="/ventech_locator/venue_display.php?id=<?= htmlspecialchars($res['venue_id'] ?? '') ?>" class="block hover:opacity-90">
                                 <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($res['venue_name'] ?? 'Venue Image') ?>" class="w-full h-48 object-cover" loading="lazy" onerror="this.onerror=null;this.src='<?= $placeholderImg ?>';">
                             </a>

                            <div class="p-4 md:p-5 flex flex-col flex-grow">
                                <h2 class="text-lg font-semibold text-gray-800 mb-2 leading-tight">
                                     <a href="/ventech_locator/venue_display.php?id=<?= htmlspecialchars($res['venue_id'] ?? '') ?>" class="hover:text-orange-600">
                                         <?= htmlspecialchars($res['venue_name'] ?? 'N/A') ?>
                                     </a>
                                </h2>

                                <p class="text-sm text-gray-600 mb-3" title="<?= htmlspecialchars($res['booker_email'] ?? 'No Email') ?>">
                                    <span class="font-medium"><i class="fas fa-user fa-fw mr-1 text-gray-400"></i>Booked by:</span>
                                    <?= htmlspecialchars(($res['first_name'] ?? '') . ' ' . ($res['last_name'] ?? '')) ?>
                                    <?php if (!empty($res['booker_username'])): ?>
                                         (<?= htmlspecialchars($res['booker_username']) ?>)
                                    <?php endif; ?>
                                </p>

                                <div class="mb-3 text-sm text-gray-500 space-y-1">
                                     <p><span class="font-medium"><i class="fas fa-calendar-alt fa-fw mr-1 text-gray-400"></i>Date:</span> <?= htmlspecialchars(date("M d, Y", strtotime($res['event_date'] ?? ''))) ?></p>
                                     <p><span class="font-medium"><i class="fas fa-clock fa-fw mr-1 text-gray-400"></i>Time:</span> <?= htmlspecialchars(date("g:i A", strtotime($res['start_time'] ?? ''))) ?> - <?= htmlspecialchars(date("g:i A", strtotime($res['end_time'] ?? '')) )?></p>
                                </div>

                                <p class="text-md text-gray-800 font-semibold mb-1">
                                    <span class="font-medium"><i class="fas fa-dollar-sign fa-fw mr-1 text-gray-400"></i>Price per Hour:</span> ₱<?= number_format($res['price_per_hour'] ?? 0, 2) ?>
                                </p>
                                 <p class="text-lg text-gray-800 font-bold mb-3">
                                    <span class="font-medium"><i class="fas fa-calculator fa-fw mr-1 text-gray-400"></i>Total Cost:</span> ₱<?= number_format($res['total_cost'] ?? 0, 2) ?>
                                </p>


                                 <p class="text-xs text-gray-400 mb-4">
                                    Requested: <?= htmlspecialchars(date("M d, Y H:i", strtotime($res['created_at'] ?? ''))) ?>
                                 </p>

                                <div class="mt-auto pt-4 border-t border-gray-200">
                                    <?php if (strtolower($res['status'] ?? '') === 'pending'): // Use ?? '' for safety ?>
                                        <p class="text-sm font-medium text-yellow-700 mb-2"><i class="fas fa-hourglass-half mr-1"></i> Action Required:</p>
                                        <div class="flex space-x-2">
                                            <form method="POST" action="reservation_manage.php" class="inline-block flex-1 accept-form">
                                                <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['id'] ?? '') ?>">
                                                <input type="hidden" name="action" value="accept">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white py-2 px-3 rounded text-xs font-medium transition duration-150 ease-in-out shadow-sm flex items-center justify-center">
                                                    <i class="fas fa-check mr-1"></i> Accept
                                                </button>
                                            </form>
                                            <form method="POST" action="reservation_manage.php" class="inline-block flex-1 reject-form">
                                                <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['id'] ?? '') ?>">
                                                <input type="hidden" name="action" value="reject">
                                                 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                 <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white py-2 px-3 rounded text-xs font-medium transition duration-150 ease-in-out shadow-sm flex items-center justify-center">
                                                    <i class="fas fa-times mr-1"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    <?php elseif (strtolower($res['status'] ?? '') === 'cancellation_requested'): ?>
                                        <p class="text-sm font-medium text-yellow-700 mb-2"><i class="fas fa-exclamation-triangle mr-1"></i> User Cancellation Requested:</p>
                                        <div class="flex space-x-2">
                                            <form method="POST" action="reservation_manage.php" class="inline-block flex-1 confirm-cancellation-form">
                                                <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['id'] ?? '') ?>">
                                                <input type="hidden" name="action" value="confirm_cancellation">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 px-3 rounded text-xs font-medium transition duration-150 ease-in-out shadow-sm flex items-center justify-center">
                                                    <i class="fas fa-check-double mr-1"></i> Confirm Cancellation
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                         <div class="flex items-center mb-2">
                                           <span class="text-sm font-medium text-gray-700 mr-2">Status:</span>
                                            <span class="px-2 py-0.5 inline-block rounded-full text-xs font-semibold <?= getStatusBadgeClass($res['status'] ?? '') ?>">
                                                 <?= htmlspecialchars(ucfirst($res['status'] ?? 'Unknown')) ?>
                                            </span>
                                       </div>
                                       <?php endif; ?>

                                     <div class="flex space-x-2 mt-3">
                                         <a href="reservation_manage.php?id=<?= htmlspecialchars($res['id'] ?? '') ?><?= ($filter_status !== 'all' ? '&status_filter=' . htmlspecialchars($filter_status) : '') ?>" class="flex-1 inline-block text-xs text-blue-600 hover:text-blue-800 font-medium py-2 px-3 rounded text-center border border-blue-600 hover:border-blue-800">
                                             <i class="fas fa-info-circle mr-1"></i> View Details
                                         </a>
                                         <?php if (strtolower($res['status'] ?? '') === 'accepted' || strtolower($res['status'] ?? '') === 'completed'): ?>
                                             <a href="/ventech_locator/print_receipt.php?id=<?= htmlspecialchars($res['id'] ?? '') ?>" target="_blank" class="flex-1 inline-block text-xs bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-3 rounded text-center shadow-sm transition duration-150 ease-in-out">
                                                 <i class="fas fa-print mr-1"></i> Print Receipt
                                             </a>
                                         <?php endif; ?>
                                     </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                     <div class="col-span-1 md:col-span-2 lg:col-span-3 bg-white rounded-lg shadow p-6 text-center text-gray-500">
                             <?php if ($filter_status === 'all'): ?>
                                 You currently have no booking requests for your venues.
                             <?php else: ?>
                                 No booking requests with status "<?= htmlspecialchars(ucfirst($filter_status)) ?>" found for your venues.
                             <?php endif; ?>
                         </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <script>
         document.addEventListener('DOMContentLoaded', () => {
             // Get the loading overlay element
             const loadingOverlay = document.getElementById('loading-overlay');

             // Hide the loading overlay after 4 seconds
             setTimeout(() => {
                 if (loadingOverlay) {
                     loadingOverlay.classList.add('hidden');
                 }
             }, 4000); // 4000 milliseconds = 4 seconds

             // SweetAlert confirmation for Reject forms
             const rejectForms = document.querySelectorAll('.reject-form');
             rejectForms.forEach(form => {
                 form.addEventListener('submit', function (event) {
                     event.preventDefault(); // Prevent the default form submission immediately

                     Swal.fire({
                         title: 'Are you sure?',
                         text: "You are about to reject this reservation. This action cannot be undone.",
                         icon: 'error', // Use 'error' or 'warning' icon for rejection
                         showCancelButton: true,
                         confirmButtonColor: '#d33', // Red color for Reject
                         cancelButtonColor: '#3085d6', // Blue color for Cancel
                         confirmButtonText: 'Yes, reject it!'
                     }).then((result) => {
                         if (result.isConfirmed) {
                             // If confirmed, submit the form programmatically
                             form.submit();
                         }
                     });
                 });
             });

             // SweetAlert confirmation for Accept forms (existing logic)
             const acceptForms = document.querySelectorAll('.accept-form');
             acceptForms.forEach(form => {
                 form.addEventListener('submit', function (event) {
                     event.preventDefault(); // Prevent the default form submission immediately

                     Swal.fire({
                         title: 'Are you sure?',
                         text: "You are about to accept this reservation.",
                         icon: 'warning', // Use 'warning' icon for confirmation
                         showCancelButton: true,
                         confirmButtonColor: '#3085d6', // Blue color for Accept
                         cancelButtonColor: '#d33', // Red color for Cancel
                         confirmButtonText: 'Yes, accept it!'
                     }).then((result) => {
                         if (result.isConfirmed) {
                             // If confirmed, submit the form programmatically
                             form.submit();
                         }
                     });
                 });
             });

             // SweetAlert confirmation for Confirm Cancellation forms
             const confirmCancellationForms = document.querySelectorAll('.confirm-cancellation-form');
             confirmCancellationForms.forEach(form => {
                 form.addEventListener('submit', function (event) {
                     event.preventDefault(); // Prevent the default form submission immediately

                     Swal.fire({
                         title: 'Confirm Cancellation?',
                         text: "You are about to confirm this user's cancellation request. This will mark the reservation as 'cancelled'.",
                         icon: 'info', // Use 'info' or 'question' icon
                         showCancelButton: true,
                         confirmButtonColor: '#10b981', // Green color for Confirm
                         cancelButtonColor: '#ef4444', // Red color for Cancel
                         confirmButtonText: 'Yes, Confirm!'
                     }).then((result) => {
                         if (result.isConfirmed) {
                             // If confirmed, submit the form programmatically
                             form.submit();
                         }
                     });
                 });
             });
         });
     </script>

</body>
</html>
