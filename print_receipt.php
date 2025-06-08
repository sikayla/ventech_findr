<?php
// print_receipt.php

// **1. Start Session**
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// **2. Include Database Connection**
require_once 'includes/db_connection.php'; // Adjust path as needed

// Ensure $pdo is available
if (!isset($pdo)) {
    error_log("PDO connection failed in print_receipt.php");
    die("System error: Database connection failed. Please try again later.");
}

// **3. Check User Authentication**
// This page should only be accessible to logged-in users (bookers or owners)
if (!isset($_SESSION['user_id'])) {
    header("Location: /ventech_locator/client/client_login.php?error=not_logged_in");
    exit;
}
$loggedInUserId = $_SESSION['user_id'];

// **4. Get Reservation ID from GET parameter**
$reservation_id = $_GET['id'] ?? null;

if (!$reservation_id) {
    die("Error: No reservation ID provided.");
}

$reservation = null;
try {
    // Fetch the reservation details, ensuring it belongs to the logged-in user (booker)
    // Joined with venue, users (for owner details), and client_info (for venue contact details)
    $sql = "SELECT
                r.id, r.venue_id, r.user_id AS booker_user_id, r.event_date, r.start_time, r.end_time,
                r.first_name, r.last_name, r.email AS booker_email, r.mobile_country_code, r.mobile_number,
                r.address, r.country, r.notes, r.voucher_code, r.total_cost, r.price_per_hour, r.status, r.created_at,
                v.title AS venue_name, v.location AS venue_location,
                u_owner.username AS owner_username, u_owner.email AS owner_email, u_owner.contact_number AS owner_phone,
                ci.client_name AS venue_contact_name, ci.client_email AS venue_contact_email, ci.client_phone AS venue_contact_phone, ci.client_address AS venue_contact_address
            FROM venue_reservations r
            JOIN venue v ON r.venue_id = v.id
            JOIN users u_owner ON v.user_id = u_owner.id
            LEFT JOIN client_info ci ON v.id = ci.venue_id -- LEFT JOIN to get client_info
            WHERE r.id = :reservation_id AND r.user_id = :booker_user_id"; // IMPORTANT CHANGE: Check if logged-in user is the booker

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':reservation_id' => $reservation_id,
        ':booker_user_id' => $loggedInUserId // Pass the logged-in user's ID as the booker ID
    ]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
        // More specific error message for the user
        die("Error: Reservation not found or you do not have permission to view this receipt. Please ensure you are logged in as the reservation's booker.");
    }

} catch (PDOException $e) {
    error_log("Database error fetching reservation for receipt: " . $e->getMessage());
    die("An error occurred while loading the receipt. Please try again later.");
}

// Helper function for status badge class (copied from reservation_manage.php for consistency)
function getStatusBadgeClass($status) {
    $status = strtolower($status ?? 'unknown');
    switch ($status) {
        case 'open': case 'confirmed': case 'accepted': case 'completed':
            return 'bg-green-100 text-green-800';
        case 'closed': case 'cancelled': case 'rejected':
            return 'bg-red-100 text-red-800';
        case 'pending': case 'cancellation_requested':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Reservation #<?= htmlspecialchars($reservation['id']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/ventech_locator/css/print_receipt.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f3f4f6; /* gray-100 */
            color: #374151; /* gray-700 */
        }
        .receipt-container {
            max-width: 800px;
            margin: 2rem auto;
            background-color: #ffffff;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            line-height: 1.6;
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e5e7eb; /* gray-200 */
            padding-bottom: 1rem;
        }
        .receipt-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937; /* gray-900 */
            margin-bottom: 0.5rem;
        }
        .receipt-header p {
            font-size: 0.9rem;
            color: #6b7280; /* gray-500 */
        }
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937; /* gray-900 */
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb; /* gray-200 */
            padding-bottom: 0.5rem;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #e5e7eb; /* gray-200 */
        }
        .detail-row:last-of-type {
            border-bottom: none;
        }
        .detail-row span:first-child {
            font-weight: 500;
            color: #4b5563; /* gray-700 */
        }
        .detail-row span:last-child {
            color: #1f2937; /* gray-900 */
        }
        .total-section {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 2px solid #1f2937; /* gray-900 */
            text-align: right;
        }
        .total-section .total-label {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937; /* gray-900 */
            margin-right: 1rem;
        }
        .total-section .total-amount {
            font-size: 2rem;
            font-weight: 700;
            color: #ef4444; /* red-500 */
        }
        .print-button-container {
            text-align: center;
            margin-top: 2rem;
        }
        @media print {
            body {
                background-color: #ffffff;
            }
            .receipt-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
            .print-button-container {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <h1>Reservation Receipt</h1>
            <p>Ventech Locator</p>
            <p>Issued: <?= htmlspecialchars(date("M d, Y H:i")) ?></p>
        </div>

        <h2 class="section-title">Reservation Details</h2>
        <div class="space-y-2">
            <div class="detail-row">
                <span>Reservation ID:</span>
                <span>#<?= htmlspecialchars($reservation['id']) ?></span>
            </div>
            <div class="detail-row">
                <span>Venue Name:</span>
                <span><?= htmlspecialchars($reservation['venue_name']) ?></span>
            </div>
            <div class="detail-row">
                <span>Event Date:</span>
                <span><?= htmlspecialchars(date("M d, Y", strtotime($reservation['event_date']))) ?></span>
            </div>
            <div class="detail-row">
                <span>Time:</span>
                <span><?= htmlspecialchars(date("g:i A", strtotime($reservation['start_time']))) ?> - <?= htmlspecialchars(date("g:i A", strtotime($reservation['end_time']))) ?></span>
            </div>
            <div class="detail-row">
                <span>Status:</span>
                <span class="px-2 py-0.5 inline-block rounded-full text-xs font-semibold <?= getStatusBadgeClass($reservation['status']) ?>">
                    <?= htmlspecialchars(ucfirst($reservation['status'])) ?>
                </span>
            </div>
            <div class="detail-row">
                <span>Price Per Hour:</span>
                <span>₱<?= number_format($reservation['price_per_hour'], 2) ?></span>
            </div>
            <div class="detail-row">
                <span>Notes:</span>
                <span><?= htmlspecialchars($reservation['notes'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($reservation['voucher_code'])): ?>
            <div class="detail-row">
                <span>Voucher Code:</span>
                <span><?= htmlspecialchars($reservation['voucher_code']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <h2 class="section-title">Booker Information</h2>
        <div class="space-y-2">
            <div class="detail-row">
                <span>Name:</span>
                <span><?= htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) ?></span>
            </div>
            <div class="detail-row">
                <span>Email:</span>
                <span><?= htmlspecialchars($reservation['booker_email']) ?></span>
            </div>
            <div class="detail-row">
                <span>Contact Number:</span>
                <span><?= htmlspecialchars($reservation['mobile_country_code'] . $reservation['mobile_number']) ?></span>
            </div>
            <div class="detail-row">
                <span>Address:</span>
                <span><?= htmlspecialchars($reservation['address'] ?? 'N/A') ?><?= !empty($reservation['country']) ? ', ' . htmlspecialchars($reservation['country']) : '' ?></span>
            </div>
        </div>

        <h2 class="section-title">Venue Contact Information</h2>
        <div class="space-y-2">
            <div class="detail-row">
                <span>Contact Name:</span>
                <span><?= htmlspecialchars($reservation['venue_contact_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span>Contact Email:</span>
                <span><?= htmlspecialchars($reservation['venue_contact_email'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span>Contact Phone:</span>
                <span><?= htmlspecialchars($reservation['venue_contact_phone'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span>Venue Address:</span>
                <span><?= htmlspecialchars($reservation['venue_contact_address'] ?? $reservation['venue_location'] ?? 'N/A') ?></span>
            </div>
        </div>

        <div class="total-section">
            <span class="total-label">Total Cost:</span>
            <span class="total-amount">₱<?= number_format($reservation['total_cost'], 2) ?></span>
        </div>

        <div class="print-button-container">
            <button onclick="window.print()" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded shadow-md transition duration-150 ease-in-out">
                Print Receipt
            </button>
        </div>
    </div>

    <script>
        // Automatically trigger print dialog when page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
