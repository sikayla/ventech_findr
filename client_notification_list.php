<?php
// **1. Start Session**
session_start();

// **2. Include Database Connection**
require_once 'includes/db_connection.php'; // Adjust path as necessary

// **3. Check User Authentication**
if (!isset($_SESSION['user_id'])) {
    header("Location: client_login.php"); // Adjust path if client_login.php is elsewhere
    exit;
}
$loggedInOwnerUserId = $_SESSION['user_id'];

// **4. Check if PDO connection is available**
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("PDO connection not available in client_notification_list.php");
    die("Sorry, we're experiencing technical difficulties with the database. Please try again later.");
}

// **5. Fetch Logged-in User Details (for display)**
try {
    $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt->execute([$loggedInOwnerUserId]);
    $owner = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$owner) {
        error_log("Invalid user_id in session: " . $loggedInOwnerUserId);
        session_unset();
        session_destroy();
        header("Location: client_login.php?error=invalid_session");
        exit;
    }
    // Optional: Restrict access if role is not 'client' or 'admin'
    if ($owner['role'] !== 'client' && $owner['role'] !== 'admin') {
        error_log("User ID {$loggedInOwnerUserId} attempted to access notifications with role: {$owner['role']}");
        header("Location: client_dashboard.php?error=unauthorized_access");
        exit;
    }

} catch (PDOException $e) {
    error_log("Error fetching user details for notifications: " . $e->getMessage());
    die("Error loading your information. Please try refreshing the page or contact support.");
}

// **6. Fetch Pending Reservations (Notifications for Owner)**
$pending_reservations = [];
try {
    // Get venue IDs owned by the logged-in user
    $stmtVenueIds = $pdo->prepare("SELECT id FROM venue WHERE user_id = ?");
    $stmtVenueIds->execute([$loggedInOwnerUserId]);
    $venue_ids_owned = $stmtVenueIds->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($venue_ids_owned)) {
        $in_placeholders = implode(',', array_fill(0, count($venue_ids_owned), '?'));
        $sql = "SELECT
                    r.id, r.event_date, r.start_time, r.end_time, r.status, r.created_at,
                    v.title AS venue_title, v.image_path,
                    u.username AS booker_username, u.email AS booker_email,
                    r.total_cost, r.price_per_hour
                FROM venue_reservations r
                JOIN venue v ON r.venue_id = v.id
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.venue_id IN ($in_placeholders) AND r.status = 'pending'
                ORDER BY r.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($venue_ids_owned);
        $pending_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching pending reservations for notification list: " . $e->getMessage());
    // Display a user-friendly message on the page
    $errorMessage = "Failed to load pending reservations. Please try again later.";
}

// Helper function to get status badge class (copied for consistency)
function getStatusBadgeClass($status) {
    $status = strtolower($status ?? 'unknown');
    switch ($status) {
        case 'open': case 'confirmed': case 'accepted': case 'completed':
            return 'bg-green-100 text-green-800';
        case 'closed': case 'cancelled': case 'rejected': case 'cancellation_requested':
            return 'bg-red-100 text-red-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Paths for navigation
$indexPath = '/ventech_locator/index.php';
$clientDashboardPath = 'client_dashboard.php';
$reservationManagePath = '/ventech_locator/reservation_manage.php';
$venueDisplayPath = '/ventech_locator/venue_display.php';
$logoutPath = '/ventech_locator/client/client_logout.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Ventech Locator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; }
        .notification-card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            transition: transform 0.2s ease-in-out;
        }
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }
        .notification-card .icon {
            font-size: 1.8rem;
            margin-right: 1.5rem;
            color: #f97316; /* Orange-500 */
        }
        .notification-card .content {
            flex-grow: 1;
        }
        .notification-card .title {
            font-weight: 600;
            font-size: 1.125rem; /* text-lg */
            color: #1f2937; /* Gray-900 */
            margin-bottom: 0.25rem;
        }
        .notification-card .message {
            font-size: 0.9rem; /* text-sm */
            color: #4b5563; /* Gray-700 */
            line-height: 1.4;
        }
        .notification-card .timestamp {
            font-size: 0.75rem; /* text-xs */
            color: #6b7280; /* Gray-500 */
            margin-top: 0.5rem;
        }
        .notification-card .actions {
            margin-left: 1.5rem;
            flex-shrink: 0;
        }
        @media (max-width: 640px) {
            .notification-card {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
            }
            .notification-card .icon {
                margin-right: 0;
                margin-bottom: 0.75rem;
            }
            .notification-card .actions {
                margin-left: 0;
                margin-top: 1rem;
                width: 100%;
                display: flex;
                justify-end;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body class="bg-gray-100">

    <nav class="bg-orange-600 p-4 text-white shadow-md sticky top-0 z-30">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center">
                <a href="<?php echo htmlspecialchars($indexPath); ?>" class="text-xl font-bold hover:text-orange-200">Ventech Locator</a>
            </div>
            <div class="flex items-center">
                <span class="mr-4 hidden sm:inline">Welcome, <?= htmlspecialchars($owner['username'] ?? 'Owner') ?>!</span>
                <a href="<?php echo htmlspecialchars($logoutPath); ?>" class="bg-white text-orange-600 hover:bg-gray-200 py-1 px-3 rounded text-sm font-medium transition duration-150 ease-in-out shadow">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto p-4 md:p-6 lg:p-8">
        <header class="bg-white shadow-md rounded-lg p-6 mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-1">Notifications</h1>
                <p class="text-gray-600 text-sm">Review new booking requests and important updates.</p>
            </div>
            <a href="<?php echo htmlspecialchars($clientDashboardPath); ?>" class="bg-orange-500 hover:bg-orange-600 text-white py-2 px-4 rounded text-sm font-medium transition duration-150 ease-in-out shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
            </a>
        </header>

        <?php if (isset($errorMessage)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert">
                <p class="font-bold"><i class="fas fa-exclamation-triangle mr-2"></i>Error!</p>
                <p><?= htmlspecialchars($errorMessage) ?></p>
            </div>
        <?php endif; ?>

        <div class="notifications-list">
            <?php if (!empty($pending_reservations)): ?>
                <?php foreach ($pending_reservations as $reservation): ?>
                    <div class="notification-card">
                        <div class="icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div class="content">
                            <div class="title">New Booking Request for <?= htmlspecialchars($reservation['venue_title'] ?? 'Your Venue') ?></div>
                            <p class="message">
                                From <strong><?= htmlspecialchars($reservation['booker_username'] ?? ($reservation['booker_email'] ?? 'A user')) ?></strong>
                                for <strong><?= htmlspecialchars(date("M d, Y", strtotime($reservation['event_date'] ?? ''))) ?></strong>
                                from <?= htmlspecialchars(date("h:i A", strtotime($reservation['start_time'] ?? ''))) ?> to <?= htmlspecialchars(date("h:i A", strtotime($reservation['end_time'] ?? ''))) ?>.
                                Total Cost: ₱<?= number_format($reservation['total_cost'] ?? 0, 2) ?>.
                            </p>
                            <p class="timestamp">Received: <?= htmlspecialchars(date("M d, Y H:i", strtotime($reservation['created_at'] ?? ''))) ?></p>
                        </div>
                        <div class="actions">
                            <a href="<?= htmlspecialchars($reservationManagePath); ?>?status_filter=pending" class="bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium py-2 px-4 rounded transition duration-150 ease-in-out shadow-sm">
                                Review Request
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow-md p-6 text-center text-gray-500">
                    <i class="fas fa-bell-slash text-4xl mb-4 text-gray-400"></i>
                    <p class="text-lg font-semibold">No new notifications.</p>
                    <p class="text-sm">Check back later for updates on your venues.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>