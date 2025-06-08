<?php
// user_reservation_manage.php

// **1. Start Session** (MUST be the very first thing)
// Ensure this is the absolute first line, before any output (including whitespace)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ** CSRF Protection Setup for Action Forms **
// Generate a fresh token for the action forms if one doesn't exist
// This token will be included in the hidden input field in the cancel forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


// **2. Include Database Connection**
require_once '../includes/db_connection.php'; // Adjust path if needed


// Ensure $pdo is available from the included file
if (!isset($pdo)) {
    error_log("PDO connection failed in user_reservation_manage.php");
    $_SESSION['message'] = "System error: Database connection failed. Please try again later.";
    $_SESSION['message_type'] = 'error';
    // Redirect to a general page or error page (adjust path as needed)
    header("Location: /ventech_locator/index.php");
    exit();
}

// **3. Check if User is Logged In**
// This page is for logged-in users to view their own reservations.
if (!isset($_SESSION['user_id'])) {
    // Redirect to login if user is not logged in (adjust path as needed)
    header("Location: /ventech_locator/client/client_login.php?error=not_logged_in");
    exit;
}
$loggedInUserId = $_SESSION['user_id']; // Get the ID of the logged-in user

// **4. Initialize Message Variable**
// Get messages from session if redirected from another page (e.g., after booking)
$message = '';
$message_type = ''; // To indicate success/error/info

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info'; // Default to info if type is not set
    unset($_SESSION['message']); // Clear the message after displaying
    unset($_SESSION['message_type']); // Clear message type
}

// **5. Handle Reservation Cancellation Request**
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'], $_POST['action']) && $_POST['action'] === 'cancel') {
    // Validate CSRF token for security
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $_SESSION['message'] = "Security error: Invalid request token.";
        $_SESSION['message_type'] = 'error';
        // Redirect to user dashboard after an error
        header("Location: /ventech_locator/user_dashboard.php");
        exit();
    }

    $reservation_id = $_POST['reservation_id'];

    try {
        // First, verify the reservation belongs to the logged-in user and its status allows cancellation
        $stmtCheck = $pdo->prepare("SELECT status FROM venue_reservations WHERE id = :id AND user_id = :user_id");
        $stmtCheck->execute([':id' => $reservation_id, ':user_id' => $loggedInUserId]);
        $reservation = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($reservation) {
            $current_status = strtolower($reservation['status']);
            // Allow cancellation only if status is pending.
            if ($current_status === 'pending') {
                $stmtUpdate = $pdo->prepare("
                    UPDATE venue_reservations
                    SET status = 'cancellation_requested', updated_at = NOW()
                    WHERE id = :id AND user_id = :user_id
                ");
                $stmtUpdate->execute([
                    ':id' => $reservation_id,
                    ':user_id' => $loggedInUserId
                ]);

                $_SESSION['message'] = "Your cancellation request for reservation #{$reservation_id} has been submitted. It is now awaiting owner confirmation.";
                $_SESSION['message_type'] = 'info'; // Changed to info/warning as it's a request, not final cancellation
            } else if ($current_status === 'cancellation_requested') {
                $_SESSION['message'] = "Reservation #{$reservation_id} is already marked as 'cancellation requested'.";
                $_SESSION['message_type'] = 'warning';
            } else {
                $_SESSION['message'] = "Reservation #{$reservation_id} cannot be cancelled as its current status is '{$current_status}'.";
                $_SESSION['message_type'] = 'warning';
            }
        } else {
            $_SESSION['message'] = "Reservation not found or you do not have permission to cancel it.";
            $_SESSION['message_type'] = 'error';
        }

    } catch (PDOException $e) {
        error_log("Database error during reservation cancellation: " . $e->getMessage());
        $_SESSION['message'] = "An error occurred while cancelling the reservation. Please try again later.";
        $_SESSION['message_type'] = 'error';
    }

    // Redirect to user_dashboard.php after processing the cancellation request
    header("Location: /ventech_locator/user_dashboard.php");
    exit();
}


// **6. Fetch Reservations for the Logged-in User**
$reservations = [];
try {
    // Fetch reservations where the user_id matches the logged-in user's ID
    // Join with the venue table to get venue details
    $sql = "SELECT
                r.id, r.venue_id, r.user_id AS booker_user_id, r.event_date, r.start_time, r.end_time,
                r.first_name, r.last_name, r.email AS booker_email, r.mobile_country_code, r.mobile_number,
                r.address, r.country, r.notes, r.voucher_code, r.total_cost, r.price_per_hour, r.status, r.created_at, r.updated_at,
                v.title AS venue_name,
                v.image_path
            FROM venue_reservations r
            JOIN venue v ON r.venue_id = v.id
            WHERE r.user_id = :user_id -- Filter by the logged-in user's ID
            ORDER BY r.created_at DESC"; // Order by most recent first

    $stmtReservations = $pdo->prepare($sql);
    $stmtReservations->execute([':user_id' => $loggedInUserId]);
    $reservations = $stmtReservations->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Failed to fetch reservations for user ID {$loggedInUserId}: " . $e->getMessage());
    $reservations = [];
    // Set a user-friendly error message
    $message = "An error occurred while loading your reservations. Please try again later.";
    $message_type = 'error';
}


// **7. Helper Functions (Copied for consistency)**

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    $status = strtolower($status ?? 'unknown');
    switch ($status) {
        case 'accepted': case 'confirmed': case 'completed':
            return 'bg-green-100 text-green-800';
        case 'cancelled': case 'rejected':
            return 'bg-red-100 text-red-800';
        case 'pending': case 'cancellation_requested': // Added cancellation_requested here to show as yellow
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Helper function to determine message alert class
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
    <title>My Reservations - Ventech Locator</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" xintegrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <style>
        body {  font-family : 'Roboto', sans-serif; }
        /* Add any additional custom styles here */
         /* Message Alert Styles */
        .message-alert {
             padding : 1rem;
             margin-bottom : 1.5rem;
             border-radius : 0.375rem;
             border-left-width : 4px;
             box-shadow : 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
        }
         /* Reservation Card Styles (similar to notification item) */
        .reservation-item {
             background-color: #ffffff; /* White */
             border-left: 4px solid #d1d5db; /* Default border color */
             padding : 1rem; /* p-4 */
             border-radius : 0.5rem; /* rounded-lg */
             box-shadow : 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1); /* shadow-sm */
             transition: background-color 0.2s ease-in-out, border-color 0.2s ease-in-out;
             display: flex; /* Use flex for layout */
             flex-direction: column; /* Stack content vertically */
             margin-bottom: 1.5rem; /* Added margin bottom for vertical spacing */
        }
        .reservation-item:last-child {
            margin-bottom: 0; /* Remove margin from the last item */
        }
        .reservation-item:hover {
             background-color: #f9fafb; /* Tailwind gray-50 */
        }
         /* Status-based border colors */
         .reservation-item.status-pending { border-left-color: #f59e0b; /* orange-500 */ }
         .reservation-item.status-accepted,
         .reservation-item.status-confirmed,
         .reservation-item.status-completed { border-left-color: #22c55e; /* green-500 */ }
         .reservation-item.status-cancelled,
         .reservation-item.status-rejected,
         .reservation-item.status-cancellation_requested { border-left-color: #ef4444; /* red-500 */ }


         /* Helper function to get status badge class (copied for consistency) */
         .status-badge {
             padding: 0.125rem 0.5rem; /* py-0.5 px-2 */
             display: inline-block;
             border-radius: 9999px; /* rounded-full */
             font-size: 0.75rem; /* text-xs */
             font-weight: 600; /* font-semibold */
         }
         .status-badge.bg-green-100 { background-color: #dcfce7; color: #166534; } /* green-100 text-green-800 */
         .status-badge.bg-red-100 { background-color: #fee2e2; color: #991b1b; } /* red-100 text-red-800 */
         .status-badge.bg-yellow-100 { background-color: #fffbeb; color: #92400e; } /* yellow-100 text-yellow-800 */
         .status-badge.bg-gray-100 { background-color: #f3f4f6; color: #374151; } /* gray-100 text-gray-800 */


    </style>
</head>
<body class="bg-gray-100 p-4 md:p-6 lg:p-8">
    <div class="max-w-3xl mx-auto"> <header class="bg-white shadow-md rounded-lg p-6 mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-1">My Venue Reservations</h1>
                <p class="text-gray-600 text-sm">View the status of your booking requests.</p>
            </div>
            
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


        <div class="space-y-4"> <?php if (count($reservations) > 0): ?>
                <?php foreach ($reservations as $res): ?>
                    <?php
                        // Image path logic (similar to reservation_manage.php)
                        $imagePathFromDB = $res['image_path'] ?? null;
                        $uploadsBaseUrl = '/ventech_locator/uploads/'; // ADJUST PATH IF NEEDED! Relative to web root.
                        $placeholderImg = 'https://via.placeholder.com/400x250/fbbf24/ffffff?text=No+Image';
                        $imgSrc = $placeholderImg;
                        if (!empty($imagePathFromDB)) {
                             // Ensure path is correctly formed and HTML escaped
                            $imgSrc = $uploadsBaseUrl . ltrim(htmlspecialchars($imagePathFromDB), '/');
                        }

                        // Determine status class for card border
                        $status_class = 'status-' . strtolower($res['status'] ?? 'unknown');
                    ?>
                    <div class="reservation-item <?= $status_class ?> rounded-lg shadow-md overflow-hidden flex flex-col transition duration-300 ease-in-out hover:shadow-lg">
                         <a href="/ventech_locator/venue_display.php?id=<?= htmlspecialchars($res['venue_id'] ?? '') ?>" class="block hover:opacity-90">
                             <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($res['venue_name'] ?? 'Venue Image') ?>" class="w-full h-48 object-cover" loading="lazy" onerror="this.onerror=null;this.src='<?= $placeholderImg ?>';">
                         </a>

                        <div class="p-4 md:p-5 flex flex-col flex-grow">
                            <h2 class="text-lg font-semibold text-gray-800 mb-2 leading-tight">
                                 <a href="/ventech_locator/venue_display.php?id=<?= htmlspecialchars($res['venue_id'] ?? '') ?>" class="hover:text-orange-600">
                                     <?= htmlspecialchars($res['venue_name'] ?? 'N/A') ?>
                                 </a>
                            </h2>

                            <p class="text-sm text-gray-600 mb-3">
                                <span class="font-medium"><i class="fas fa-user fa-fw mr-1 text-gray-400"></i>Booked by:</span>
                                <?= htmlspecialchars(($res['first_name'] ?? '') . ' ' . ($res['last_name'] ?? '')) ?>
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

                            <div class="pt-4 border-t border-gray-200">
                                 <div class="flex items-center mb-2">
                                    <span class="text-sm font-medium text-gray-700 mr-2">Status:</span>
                                    <span class="px-2 py-0.5 inline-block rounded-full text-xs font-semibold <?= getStatusBadgeClass($res['status'] ?? '') ?>">
                                         <?= htmlspecialchars(ucfirst($res['status'] ?? 'Unknown')) ?>
                                    </span>
                                </div>

                                <?php // Add Cancel button for pending reservations only ?>
                                <?php if (strtolower($res['status'] ?? '') === 'pending'): ?>
                                    <div class="mt-3">
                                        <form method="POST" class="inline-block w-full cancel-form">
                                            <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['id'] ?? '') ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white py-2 px-3 rounded text-xs font-medium transition duration-150 ease-in-out shadow-sm flex items-center justify-center">
                                                <i class="fas fa-times-circle mr-1"></i> Cancel Reservation
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>

                                <div class="flex space-x-2 mt-3">
                                    <a href="/ventech_locator/reservation_manage_details.php?id=<?= htmlspecialchars($res['id'] ?? '') ?>" class="flex-1 inline-block text-xs text-blue-600 hover:text-blue-800 font-medium py-2 px-3 rounded text-center border border-blue-600 hover:border-blue-800">
                                        <i class="fas fa-info-circle mr-1"></i> View Full Details
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
                         You currently have no venue reservations.
                     </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // SweetAlert confirmation for Cancel forms
            const cancelForms = document.querySelectorAll('.cancel-form');
            cancelForms.forEach(form => {
                form.addEventListener('submit', function (event) {
                    event.preventDefault(); // Prevent the default form submission immediately

                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You are about to cancel this reservation. This action cannot be undone.",
                        icon: 'warning', // Use 'warning' icon for cancellation
                        showCancelButton: true,
                        confirmButtonColor: '#d33', // Red color for "Yes, cancel it!"
                        cancelButtonColor: '#3085d6', // Blue color for "No, keep it"
                        confirmButtonText: 'Yes, cancel it!',
                        cancelButtonText: 'No, keep it'
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
